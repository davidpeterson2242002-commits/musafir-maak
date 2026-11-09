<?php

namespace App\Services;

use App\Enums\DocumentKind;
use App\Enums\DriverStatus;
use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Enums\NotificationEvent;
use App\Enums\ReviewStatus;
use App\Enums\VehicleStatus;
use App\Models\Admin;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Submission and review of passenger IDs, driver applications and vehicles (spec 5.1, 5.12, 9.2).
 *
 * A driver application includes the user's identity documents (unless already verified),
 * the driving licence and the vehicles added during registration; approving it verifies
 * all of them at once. Vehicles added after approval are reviewed on their own.
 */
class VerificationService
{
    public function __construct(
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------------ Submissions

    public function submitIdentity(User $user, IdDocumentType $type): VerificationReview
    {
        if (! in_array($user->id_verification_status, [IdVerificationStatus::None, IdVerificationStatus::Rejected], true)) {
            throw ValidationException::withMessages(['identity' => match ($user->id_verification_status) {
                IdVerificationStatus::Pending => 'هويتك قيد المراجعة.',
                default => 'هويتك موثّقة مسبقاً.',
            }]);
        }

        $this->assertIdentityDocuments($user, $type);

        return DB::transaction(function () use ($user, $type) {
            $user->forceFill(['id_verification_status' => IdVerificationStatus::Pending])->save();

            return $user->verificationReviews()->create([
                'status' => ReviewStatus::Pending,
                'id_document_type' => $type,
            ]);
        });
    }

    public function submitDriver(DriverProfile $driver, ?IdDocumentType $idType): VerificationReview
    {
        $driver->load(['user.documents', 'documents', 'vehicles.documents']);
        $user = $driver->user;

        if (! $driver->status->isEditable()) {
            throw ValidationException::withMessages(['driver' => 'طلبك قيد المراجعة أو تمت الموافقة عليه.']);
        }

        $errors = [];
        $includesIdentity = $user->id_verification_status !== IdVerificationStatus::Verified;

        if ($includesIdentity) {
            if ($idType === null) {
                $errors['id_document_type'] = 'اختر نوع الهوية (جواز أو بطاقة قومية).';
            } elseif ($missing = $user->missingDocuments($idType->requiredKinds())) {
                $errors['identity'] = 'مستندات الهوية الناقصة: '.$this->labels($missing);
            }
        }

        if ($driver->missingDocuments(DocumentKind::driverKinds())) {
            $errors['driving_licence'] = 'ارفع صورة رخصة القيادة.';
        }

        $vehicles = $driver->vehicles->filter(fn (Vehicle $v) => $v->status->isEditable());
        if ($vehicles->isEmpty()) {
            $errors['vehicles'] = 'أضف عربية واحدة على الأقل.';
        }
        foreach ($vehicles as $vehicle) {
            if ($missing = $vehicle->missingDocuments(DocumentKind::vehicleKinds())) {
                $errors["vehicles.{$vehicle->id}"] = "صور ناقصة للعربية {$vehicle->plate_number}: ".$this->labels($missing);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($driver, $user, $vehicles, $includesIdentity, $idType) {
            $driver->forceFill([
                'status' => DriverStatus::PendingReview,
                'submitted_at' => now(),
                'terms_accepted_at' => $driver->terms_accepted_at ?? now(),
            ])->save();

            foreach ($vehicles as $vehicle) {
                $vehicle->forceFill(['status' => VehicleStatus::Pending, 'submitted_at' => now()])->save();
            }

            if ($includesIdentity) {
                $user->forceFill(['id_verification_status' => IdVerificationStatus::Pending])->save();
            }

            return $driver->verificationReviews()->create([
                'status' => ReviewStatus::Pending,
                'id_document_type' => $includesIdentity ? $idType : null,
            ]);
        });
    }

    /** A vehicle added after the driver was approved. */
    public function submitVehicle(Vehicle $vehicle): VerificationReview
    {
        $vehicle->load(['driverProfile', 'documents']);

        if (! $vehicle->driverProfile->isActive()) {
            throw ValidationException::withMessages(['vehicle' => 'العربيات تُرسل مع طلب التسجيل كسائق.']);
        }
        if (! $vehicle->status->isEditable()) {
            throw ValidationException::withMessages(['vehicle' => 'هذه العربية قيد المراجعة أو معتمدة.']);
        }
        if ($missing = $vehicle->missingDocuments(DocumentKind::vehicleKinds())) {
            throw ValidationException::withMessages(['documents' => 'صور ناقصة: '.$this->labels($missing)]);
        }

        return DB::transaction(function () use ($vehicle) {
            $vehicle->forceFill(['status' => VehicleStatus::Pending, 'submitted_at' => now()])->save();

            return $vehicle->verificationReviews()->create(['status' => ReviewStatus::Pending]);
        });
    }

    // ------------------------------------------------------------------ Reviews

    /** Mark the item as being reviewed by $admin (soft lock for multiple reviewers). */
    public function claim(VerificationReview $review, Admin $admin): void
    {
        if ($review->isPending() && ! $review->isClaimedByOther($admin)) {
            $review->forceFill(['assigned_to' => $admin->id, 'assigned_at' => now()])->save();
        }
    }

    public function approve(VerificationReview $review, Admin $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($review, $admin, $note) {
            $review = $this->lockPending($review);
            $subject = $review->subject;

            match (true) {
                $subject instanceof User => $this->approveIdentity($subject),
                $subject instanceof DriverProfile => $this->approveDriver($subject),
                $subject instanceof Vehicle => $this->approveVehicle($subject),
            };

            $this->close($review, ReviewStatus::Approved, $admin, [], $note);
        });
    }

    /**
     * Reject (or request more info). The user can fix and resubmit.
     *
     * @param  array<int, string>  $reasons  chosen from settings verification.rejection_reasons
     * @param  bool  $identityOk  driver applications only: the ID itself is fine, verify it anyway
     */
    public function reject(
        VerificationReview $review,
        Admin $admin,
        array $reasons,
        ?string $note = null,
        bool $infoRequested = false,
        bool $identityOk = false,
    ): void {
        $reasons = array_values(array_filter($reasons));
        if ($reasons === [] && blank($note)) {
            throw new InvalidArgumentException('A reason or a note is required.');
        }

        DB::transaction(function () use ($review, $admin, $reasons, $note, $infoRequested, $identityOk) {
            $review = $this->lockPending($review);
            $subject = $review->subject;

            if ($subject instanceof User) {
                $subject->forceFill(['id_verification_status' => IdVerificationStatus::Rejected])->save();
            } elseif ($subject instanceof DriverProfile) {
                $subject->forceFill(['status' => DriverStatus::Rejected])->save();
                $subject->vehicles()->where('status', VehicleStatus::Pending)->update(['status' => VehicleStatus::Rejected]);

                if ($review->id_document_type !== null) {
                    $identityOk
                        ? $this->approveIdentity($subject->user, notify: false, viaDriver: true)
                        : $this->rejectIdentityWithDriver($subject->user);
                }
            } elseif ($subject instanceof Vehicle) {
                $subject->forceFill(['status' => VehicleStatus::Rejected])->save();
            }

            $this->close($review, $infoRequested ? ReviewStatus::InfoRequested : ReviewStatus::Rejected, $admin, $reasons, $note);

            [$event, $title] = match (true) {
                $subject instanceof User => [NotificationEvent::IdRejected, 'لم يتم توثيق هويتك'],
                $subject instanceof DriverProfile => [NotificationEvent::DriverRejected, 'طلب التسجيل كسائق يحتاج تعديل'],
                $subject instanceof Vehicle => [NotificationEvent::VehicleRejected, 'العربية تحتاج تعديل'],
            };
            $owner = $subject instanceof User ? $subject : ($subject instanceof Vehicle ? $subject->driverProfile->user : $subject->user);
            $body = implode('، ', $reasons) ?: $note;

            $this->notifier->send($owner, $event, $title, $body.' — افتح التطبيق لتعديل المستندات وإعادة الإرسال.', ['review_id' => $review->id]);
        });
    }

    // ------------------------------------------------------------------ Internals

    /** @param bool $viaDriver identity approved as part of a driver application */
    private function approveIdentity(User $user, bool $notify = true, bool $viaDriver = false): void
    {
        $user->forceFill(['id_verification_status' => IdVerificationStatus::Verified])->save();

        if ($viaDriver) {
            // A separate pending passenger-ID review is settled by this decision too.
            $user->verificationReviews()->pending()->update([
                'status' => ReviewStatus::Approved, 'reviewed_at' => now(), 'note' => 'وُثّقت ضمن طلب السائق',
            ]);
        }

        if ($notify) {
            $this->notifier->send($user, NotificationEvent::IdApproved, 'تم توثيق هويتك', 'أصبح لديك شارة التوثيق ويمكنك الحجز.');
        }
    }

    private function rejectIdentityWithDriver(User $user): void
    {
        if ($user->id_verification_status === IdVerificationStatus::Pending) {
            $user->forceFill(['id_verification_status' => IdVerificationStatus::Rejected])->save();
            $user->verificationReviews()->pending()->update([
                'status' => ReviewStatus::Rejected, 'reviewed_at' => now(), 'note' => 'رُفضت ضمن طلب السائق',
            ]);
        }
    }

    private function approveDriver(DriverProfile $driver): void
    {
        $firstApproval = $driver->approved_at === null;

        $driver->forceFill([
            'status' => DriverStatus::Active,
            'approved_at' => $driver->approved_at ?? now(),
            'free_bookings_remaining' => $firstApproval ? settings()->int('drivers.free_bookings') : $driver->free_bookings_remaining,
        ])->save();

        $driver->vehicles()->where('status', VehicleStatus::Pending)
            ->update(['status' => VehicleStatus::Approved, 'approved_at' => now()]);

        if ($driver->user->id_verification_status !== IdVerificationStatus::Verified) {
            $this->approveIdentity($driver->user, notify: false, viaDriver: true);
        }

        $this->notifier->send(
            $driver->user,
            NotificationEvent::DriverApproved,
            'مبروك! تم قبولك كسائق',
            'يمكنك الآن إضافة رحلاتك من وضع السائق.'.($firstApproval && $driver->free_bookings_remaining ? " أول {$driver->free_bookings_remaining} حجوزات مجانية." : ''),
        );
    }

    private function approveVehicle(Vehicle $vehicle): void
    {
        $vehicle->forceFill(['status' => VehicleStatus::Approved, 'approved_at' => now()])->save();

        $this->notifier->send(
            $vehicle->driverProfile->user,
            NotificationEvent::VehicleApproved,
            'تم اعتماد العربية',
            "العربية {$vehicle->plate_number} جاهزة لإضافة الرحلات.",
        );
    }

    private function lockPending(VerificationReview $review): VerificationReview
    {
        $locked = VerificationReview::whereKey($review->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw new RuntimeException('تمت مراجعة هذا الطلب مسبقاً.');
        }

        return $locked->load('subject');
    }

    private function close(VerificationReview $review, ReviewStatus $status, Admin $admin, array $reasons, ?string $note): void
    {
        $review->forceFill([
            'status' => $status,
            'reasons' => $reasons ?: null,
            'note' => $note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log('verification.'.$status->value, $review, array_filter([
            'subject' => $review->subject_type.'#'.$review->subject_id,
            'reasons' => $reasons ?: null,
            'note' => $note,
        ]), $admin);
    }

    private function assertIdentityDocuments(User $user, IdDocumentType $type): void
    {
        $user->load('documents');
        if ($missing = $user->missingDocuments($type->requiredKinds())) {
            throw ValidationException::withMessages(['documents' => 'المستندات الناقصة: '.$this->labels($missing)]);
        }
    }

    /** @param array<int, DocumentKind> $kinds */
    private function labels(array $kinds): string
    {
        return implode('، ', array_map(fn (DocumentKind $k) => $k->label(), $kinds));
    }
}

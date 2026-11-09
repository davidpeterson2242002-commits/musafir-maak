<?php

namespace App\Services;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Parcel;
use App\Models\Report;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** User reports (spec 5.10, P7/P10/P13). */
class ReportService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(User $reporter, User $reported, ReportReason $reason, ?string $details, ?Model $subject): Report
    {
        if ($reporter->id === $reported->id) {
            throw new BusinessRuleException('لا يمكنك الإبلاغ عن نفسك.', 'self_report');
        }
        if (Report::where('reporter_id', $reporter->id)->where('created_at', '>=', now()->subDay())->count() >= settings()->int('reports.max_per_day')) {
            throw new BusinessRuleException('أرسلت بلاغات كثيرة اليوم. حاول لاحقاً أو تواصل مع الدعم.', 'too_many_reports');
        }
        if ($subject !== null && ! $this->involves($subject, $reporter, $reported)) {
            throw new BusinessRuleException('هذا البلاغ لا يخص رحلة أو محادثة بينكما.', 'unrelated_subject');
        }

        return Report::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'details' => $details,
            'priority' => $reason->isSafetyCritical() ? 1 : 0,
            'status' => ReportStatus::Open,
        ]);
    }

    public function startReview(Report $report, Admin $admin): void
    {
        if ($report->status === ReportStatus::Open) {
            $report->forceFill(['status' => ReportStatus::InReview, 'handled_by' => $admin->id])->save();
        }
    }

    public function resolve(Report $report, Admin $admin, string $note): void
    {
        $report->forceFill(['status' => ReportStatus::Resolved, 'resolution_note' => $note, 'handled_by' => $admin->id, 'handled_at' => now()])->save();
        $this->audit->log('report.resolved', $report, ['note' => $note], $admin);
    }

    /** The subject must connect the two users (their booking, parcel, trip or chat). */
    private function involves(Model $subject, User $a, User $b): bool
    {
        $pair = fn (int $x, int $y) => [$x, $y] == [$a->id, $b->id] || [$x, $y] == [$b->id, $a->id];

        return match (true) {
            $subject instanceof Booking => $pair($subject->passenger_id, $subject->trip->driverProfile->user_id),
            $subject instanceof Parcel => $pair($subject->sender_id, $subject->trip->driverProfile->user_id),
            $subject instanceof Conversation => $pair($subject->customer_id, $subject->driver_user_id),
            $subject instanceof Trip => in_array($subject->driverProfile->user_id, [$a->id, $b->id], true)
                && $subject->bookings()->whereIn('passenger_id', [$a->id, $b->id])->exists(),
            default => false,
        };
    }
}

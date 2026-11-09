<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\ParcelStatus;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Events\BookingCancelled;
use App\Models\Admin;
use App\Models\EnforcementAction;
use App\Models\Report;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Warn / suspend / ban / reactivate (spec 5.10). Every action is recorded and the user is told. */
class EnforcementService
{
    public function __construct(
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    public function warn(User $user, string $reason, Admin $admin, ?Report $report = null): EnforcementAction
    {
        $action = $this->record($user, 'warn', null, $reason, $admin, $report);
        $this->notifier->send($user, NotificationEvent::AccountWarning, 'تنبيه من مسافر معاك', $reason);

        return $action;
    }

    public function suspend(User $user, Carbon $until, string $reason, Admin $admin, ?Report $report = null): EnforcementAction
    {
        $user->forceFill(['status' => UserStatus::Suspended, 'suspended_until' => $until])->save();
        $action = $this->record($user, 'suspend', $until, $reason, $admin, $report);
        $this->notifier->send($user, NotificationEvent::AccountWarning, 'تم إيقاف حسابك مؤقتاً',
            'حتى '.local_time($until)->format('Y-m-d H:i').". السبب: $reason");

        return $action;
    }

    /**
     * Ban: sign out everywhere, cancel the user's upcoming trips (passengers notified, fees reversed)
     * and their open bookings and parcel requests.
     */
    public function ban(User $user, string $reason, Admin $admin, ?Report $report = null): EnforcementAction
    {
        $action = DB::transaction(function () use ($user, $reason, $admin, $report) {
            $user->forceFill(['status' => UserStatus::Banned, 'suspended_until' => null])->save();
            $user->tokens()->delete();

            if ($driver = $user->driverProfile()->first()) {
                Trip::where('driver_profile_id', $driver->id)->where('status', TripStatus::Scheduled)->get()
                    ->each(fn (Trip $trip) => app(TripService::class)->cancel($trip, 'تم إيقاف السائق من التطبيق، نعتذر عن الإزعاج.', $admin));
            }

            foreach ($user->bookings()->whereIn('status', BookingStatus::active())->with('trip')->get() as $booking) {
                $wasAccepted = $booking->status === BookingStatus::Accepted;
                if ($wasAccepted) {
                    $booking->trip->increment('seats_available', $booking->seats);
                }
                $booking->forceFill(['status' => BookingStatus::CancelledByPassenger, 'cancelled_at' => now(), 'cancel_reason' => 'إيقاف الحساب'])->save();
                if ($wasAccepted) {
                    DB::afterCommit(fn () => BookingCancelled::dispatch($booking));
                }
            }

            foreach ($user->parcels()->whereIn('status', [ParcelStatus::Pending, ParcelStatus::Accepted])->get() as $parcel) {
                app(ParcelService::class)->cancelBySender($parcel, 'إيقاف الحساب');
            }

            return $this->record($user, 'ban', null, $reason, $admin, $report);
        });

        // Banned users have no app session left; WhatsApp tells them why.
        $this->notifier->send($user, NotificationEvent::AccountWarning, 'تم حظر حسابك', "السبب: $reason. للاستفسار تواصل مع الدعم.");

        return $action;
    }

    public function reactivate(User $user, string $reason, Admin $admin): EnforcementAction
    {
        $user->forceFill(['status' => UserStatus::Active, 'suspended_until' => null])->save();

        return $this->record($user, 'reactivate', null, $reason, $admin, null);
    }

    private function record(User $user, string $action, ?Carbon $until, string $reason, Admin $admin, ?Report $report): EnforcementAction
    {
        $entry = EnforcementAction::create([
            'user_id' => $user->id,
            'action' => $action,
            'until' => $until,
            'reason' => $reason,
            'report_id' => $report?->id,
            'admin_id' => $admin->id,
        ]);

        $past = ['warn' => 'warned', 'suspend' => 'suspended', 'ban' => 'banned', 'reactivate' => 'reactivated'][$action];
        $this->audit->log('user.'.$past, $user, array_filter(['until' => $until?->toIso8601String(), 'reason' => $reason, 'report_id' => $report?->id]), $admin);

        return $entry;
    }
}

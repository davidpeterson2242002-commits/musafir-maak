<?php

namespace App\Jobs;

use App\Enums\NotificationChannelType;
use App\Enums\ParcelStatus;
use App\Models\NotificationLog;
use App\Models\Parcel;
use App\Services\NotificationLogger;
use App\Services\ParcelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** WhatsApp message with the delivery code for a parcel's receiver (spec 5.6 / S4). */
class SendParcelCodeToReceiver implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 180];

    public function __construct(public int $parcelId) {}

    public function handle(ParcelService $parcels, NotificationLogger $log): void
    {
        $parcel = Parcel::with(['sender', 'trip.driverProfile.user', 'trip.vehicle', 'trip.route.destination'])->find($this->parcelId);

        // Cancelled or refused before the message went out: the code is no longer useful.
        if ($parcel === null || ! in_array($parcel->status, [ParcelStatus::Accepted, ParcelStatus::PickedUp], true)) {
            return;
        }

        $parcels->sendReceiverMessage($parcel);

        $log->record(NotificationChannelType::WhatsApp, 'parcel_delivery_code', NotificationLog::SENT, phone: $parcel->receiver_phone);
    }

    public function failed(?Throwable $e): void
    {
        app(NotificationLogger::class)->record(
            NotificationChannelType::WhatsApp, 'parcel_delivery_code', NotificationLog::FAILED,
            phone: Parcel::find($this->parcelId)?->receiver_phone, error: $e?->getMessage(),
        );
    }
}

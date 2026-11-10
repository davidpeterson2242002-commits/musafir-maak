<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\BroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Fans an admin broadcast out to its audience; each recipient/channel becomes its own DeliverNotification job. */
class SendBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // never re-send a half-delivered broadcast automatically

    public int $timeout = 600;

    public function __construct(public int $broadcastId) {}

    public function handle(BroadcastService $broadcasts): void
    {
        $broadcast = Broadcast::find($this->broadcastId);

        if ($broadcast !== null && $broadcast->status === 'queued') {
            $broadcasts->deliver($broadcast);
        }
    }

    public function failed(?Throwable $e): void
    {
        Broadcast::whereKey($this->broadcastId)->update(['status' => 'failed']);
    }
}

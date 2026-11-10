<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationEvent;
use App\Enums\UserStatus;
use App\Jobs\SendBroadcast;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Admin messages to a segment of users (spec 9.10). */
class BroadcastService
{
    public function __construct(private Notifier $notifier, private AuditLogger $audit) {}

    /**
     * Users in a segment. Banned and deleted accounts never receive broadcasts.
     *
     * @param  array{city_id?: int|null, origin_city_id?: int|null, destination_city_id?: int|null}  $filters
     */
    public function audience(string $segment, array $filters = []): Builder
    {
        $query = User::query()->where('status', '!=', UserStatus::Banned)->whereNotNull('name');
        $drivers = fn (Builder $q) => $q->whereIn('status', [DriverStatus::Active, DriverStatus::Frozen]);

        return match ($segment) {
            'all_users' => $query,
            'users_in_city' => $query->where('city_id', $filters['city_id'] ?? 0),
            'all_drivers' => $query->whereHas('driverProfile', $drivers),
            'drivers_in_city' => $query->where('city_id', $filters['city_id'] ?? 0)->whereHas('driverProfile', $drivers),
            'drivers_owing' => $query->whereHas('driverProfile', fn (Builder $q) => $q->where('balance_owed', '>', 0)),
            'searched_route' => $query->whereIn('id', SearchLog::query()
                ->where('origin_city_id', $filters['origin_city_id'] ?? 0)
                ->where('destination_city_id', $filters['destination_city_id'] ?? 0)
                ->where('created_at', '>=', now()->subDays(settings()->int('notifications.broadcast_search_days')))
                ->whereNotNull('user_id')
                ->select('user_id')),
            default => throw new InvalidArgumentException("Unknown broadcast segment [$segment]."),
        };
    }

    /**
     * @param  array<int, string>  $channels  NotificationChannelType values
     */
    public function create(?Admin $admin, string $segment, array $filters, array $channels, string $title, string $body): Broadcast
    {
        $channels = array_values(array_intersect(array_column(NotificationChannelType::cases(), 'value'), $channels));
        if ($channels === []) {
            throw new InvalidArgumentException('A broadcast needs at least one channel.');
        }

        $broadcast = DB::transaction(function () use ($admin, $segment, $filters, $channels, $title, $body) {
            $broadcast = Broadcast::create([
                'admin_id' => $admin?->id,
                'segment' => $segment,
                'filters' => array_filter($filters, fn ($v) => $v !== null) ?: null,
                'channels' => $channels,
                'title' => $title,
                'body' => $body,
                'recipients_count' => $this->audience($segment, $filters)->count(),
            ]);

            $this->audit->log('broadcast.sent', $broadcast, [
                'segment' => $segment, 'channels' => implode(',', $channels), 'recipients' => $broadcast->recipients_count, 'title' => $title,
            ], $admin);

            return $broadcast;
        });

        SendBroadcast::dispatch($broadcast->id);

        return $broadcast->refresh();
    }

    /** Fan out to the audience (called by the SendBroadcast job). Returns the number of users notified. */
    public function deliver(Broadcast $broadcast): int
    {
        $channels = array_map(fn (string $c) => NotificationChannelType::from($c), $broadcast->channels);
        $count = 0;

        $broadcast->update(['status' => 'sending']);

        $this->audience($broadcast->segment, $broadcast->filters ?? [])
            ->chunkById(500, function ($users) use ($broadcast, $channels, &$count) {
                foreach ($users as $user) {
                    $this->notifier->send($user, NotificationEvent::Broadcast, $broadcast->title, $broadcast->body,
                        ['broadcast_id' => $broadcast->id], $channels, $broadcast->id);
                    $count++;
                }
            });

        $broadcast->update(['status' => 'sent', 'sent_at' => now(), 'recipients_count' => $count]);

        return $count;
    }
}

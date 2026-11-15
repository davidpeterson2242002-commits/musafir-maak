<?php

namespace App\Support;

use App\Models\MetricMinute;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Live API traffic for the admin dashboard, without any external service.
 *
 * Every API request bumps a few per-minute counters in the file cache (cheap, no DB write);
 * the per-minute scheduler copies finished minutes into `metric_minutes` for history.
 */
class ApiMetrics
{
    private const TTL = 900; // counters live 15 minutes

    public const SLOW_MS = 1000;

    public static function store(): Repository
    {
        return Cache::store(config('cache.metrics_store', 'file'));
    }

    private static function key(Carbon|string $minute, string $field): string
    {
        $m = $minute instanceof Carbon ? $minute->copy()->utc()->format('YmdHi') : $minute;

        return "apim:$m:$field";
    }

    /** Called once per API request after the response is sent. Never throws. */
    public static function record(int $ms, int $status): void
    {
        try {
            $store = self::store();
            $minute = now()->utc()->format('YmdHi');
            foreach (['r' => 1, 't' => $ms, 'e' => $status >= 500 ? 1 : 0, 's' => $ms >= self::SLOW_MS ? 1 : 0] as $field => $by) {
                if ($by === 0) {
                    continue;
                }
                $key = self::key($minute, $field);
                $store->add($key, 0, self::TTL);
                $store->increment($key, $by);
            }
            $maxKey = self::key($minute, 'x');
            if ($ms > (int) $store->get($maxKey, 0)) {
                $store->put($maxKey, $ms, self::TTL);
            }
        } catch (Throwable) {
            // Metrics must never break a request.
        }
    }

    /** @return array{requests:int, errors:int, slow:int, total_ms:int, max_ms:int} */
    public static function minute(Carbon $minute): array
    {
        $store = self::store();
        $get = fn (string $f) => (int) $store->get(self::key($minute, $f), 0);

        return ['requests' => $get('r'), 'errors' => $get('e'), 'slow' => $get('s'), 'total_ms' => $get('t'), 'max_ms' => $get('x')];
    }

    public static function onlineNow(int $minutes = 2): int
    {
        return User::where('last_seen_at', '>=', now()->subMinutes($minutes))->count();
    }

    /** Copies the last few finished minutes into metric_minutes (idempotent). Run every minute. */
    public static function flush(): int
    {
        $written = 0;
        $online = self::onlineNow();
        for ($i = 1; $i <= 5; $i++) {
            $minute = now()->utc()->startOfMinute()->subMinutes($i);
            $row = self::minute($minute);
            if ($row['requests'] === 0 && $i > 1) {
                continue;
            }
            MetricMinute::updateOrCreate(['minute' => $minute], [...$row, ...($i === 1 ? ['online' => $online] : [])]);
            $written++;
        }

        return $written;
    }

    public static function prune(int $days = 14): int
    {
        return MetricMinute::where('minute', '<', now()->subDays($days))->delete();
    }

    /**
     * Everything the "live" dashboard tab shows, in one cached call (5 s) so several admins
     * watching at once cost almost nothing.
     */
    public static function snapshot(): array
    {
        return Cache::remember('dashboard.live.v1', 5, function () {
            $now = now()->utc()->startOfMinute();
            $current = self::minute($now);
            $previous = self::minute($now->copy()->subMinute());
            $secondsIn = max(1, now()->second);

            // Requests per second: last full minute, or the running minute if it is busier.
            $rps = max($previous['requests'] / 60, $current['requests'] / $secondsIn);
            $last = $previous['requests'] ? $previous : $current;
            $avgMs = $last['requests'] ? (int) round($last['total_ms'] / $last['requests']) : 0;

            $history = MetricMinute::where('minute', '>=', now()->subHour())->orderBy('minute')->get();
            $online = self::onlineNow();
            $peakOnline = (int) MetricMinute::where('minute', '>=', now()->subDay())->max('online');

            // How many requests one person sends (the app polls every ~12 s while open).
            $recent = $history->where('online', '>', 0)->take(-15);
            $perUser = $recent->sum('online') > 0 ? $recent->sum('requests') / 60 / $recent->sum('online') * 1 : null;
            $perUser = $perUser && $perUser > 0.01 ? $perUser : settings()->float('ops.requests_per_user_per_second');

            $capacityRps = settings()->float('ops.capacity_rps');
            $capacityUsers = $capacityRps > 0 && $perUser > 0 ? (int) floor($capacityRps / $perUser) : null;
            $load = $capacityRps > 0 ? min(1.5, $rps / $capacityRps) : null;
            $errorRate = $last['requests'] ? $last['errors'] / $last['requests'] : 0.0;

            return [
                'online' => $online,
                'online_5m' => self::onlineNow(5),
                'online_today' => User::where('last_seen_at', '>=', now(config('app.display_timezone'))->startOfDay())->count(),
                'peak_online_24h' => max($peakOnline, $online),
                'rps' => round($rps, 2),
                'requests_last_minute' => $previous['requests'],
                'avg_ms' => $avgMs,
                'max_ms' => $last['max_ms'],
                'slow_last_minute' => $last['slow'],
                'errors_last_minute' => $last['errors'],
                'error_rate' => $errorRate,
                'per_user_rps' => round($perUser, 3),
                'capacity_rps' => $capacityRps,
                'capacity_users' => $capacityUsers,
                'load' => $load,
                'status' => self::status($load, $errorRate, $avgMs),
                'queue_waiting' => self::safeCount('jobs'),
                'queue_failed_24h' => self::safeCount('failed_jobs', 'failed_at'),
                'notifications_failed_1h' => DB::table('notification_logs')->where('status', 'failed')->where('created_at', '>=', now()->subHour())->count(),
                'last_tick' => Cache::get('musafir.last_tick'),
                'history' => $history->map(fn (MetricMinute $m) => [
                    'minute' => local_time($m->minute)->format('H:i'),
                    'requests' => $m->requests,
                    'avg_ms' => $m->requests ? (int) round($m->total_ms / $m->requests) : 0,
                    'errors' => $m->errors,
                    'online' => $m->online,
                ])->values()->all(),
                'at' => local_time(now())->format('H:i:s'),
            ];
        });
    }

    /** green / amber / red, from load against measured capacity, errors and speed. */
    public static function status(?float $load, float $errorRate, int $avgMs): string
    {
        if (($load !== null && $load >= 0.85) || $errorRate >= 0.05 || $avgMs >= 2000) {
            return 'red';
        }
        if (($load !== null && $load >= 0.6) || $errorRate >= 0.01 || $avgMs >= 800) {
            return 'amber';
        }

        return 'green';
    }

    private static function safeCount(string $table, ?string $since = null): int
    {
        try {
            return DB::table($table)->when($since, fn ($q) => $q->where($since, '>=', now()->subDay()))->count();
        } catch (Throwable) {
            return 0;
        }
    }
}

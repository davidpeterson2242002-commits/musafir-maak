<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Banned users lose API access (tokens revoked). Suspended users can still read
 * (bookings, chat) — write actions check isSuspended() in their own rules.
 */
class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isBanned()) {
            $user->tokens()->delete();
            app(\App\Services\TrustedDeviceService::class)->forgetAll($user);

            return response()->json(['message' => 'تم حظر هذا الحساب. للاستفسار تواصل مع الدعم.', 'code' => 'account_banned'], 403);
        }

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(10)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}

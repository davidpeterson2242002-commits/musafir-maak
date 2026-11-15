<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds `Server-Timing: app;dur=…, db;dur=…;desc="N queries"` to API responses, so slow
 * screens can be measured from the outside without logs or user data.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $queries = 0;
        $dbMs = 0.0;
        DB::listen(function ($query) use (&$queries, &$dbMs) {
            $queries++;
            $dbMs += $query->time;
        });

        $response = $next($request);

        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.0f, db;dur=%.0f;desc="%d queries"',
            (microtime(true) - $start) * 1000, $dbMs, $queries,
        ));

        return $response;
    }
}

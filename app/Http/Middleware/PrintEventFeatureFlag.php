<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to gate PrintEvent API endpoints behind a feature flag.
 *
 * When NEXUS_PRINT_EVENTS_ENABLED=false (MVP default), Nexus does not
 * execute printer work. woosoo-print-bridge is the sole active print
 * execution path. This middleware returns 503 Service Unavailable for
 * PrintEvent endpoints when the feature is disabled.
 */
class PrintEventFeatureFlag
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $enabled = config('api.print_events_enabled', false);

        if (! $enabled) {
            Log::info('PrintEvent flow disabled: woosoo-print-bridge is primary print execution path');

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRINT_EVENTS_DISABLED',
                    'message' => 'PrintEvent processing is disabled. woosoo-print-bridge is the active print execution path.',
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id', ''),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 503);
        }

        return $next($request);
    }
}

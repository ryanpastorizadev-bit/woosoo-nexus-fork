<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Middleware to exempt only stateless device/printer API endpoints from CSRF.
 *
 * SECURITY NOTE: We use EXPLICIT endpoint patterns instead of broad wildcards
 * to prevent accidentally exempting session-authenticated admin routes.
 *
 * CSRF is required for:
 * - Any route using auth:sanctum with session cookies (admin web UI)
 * - Any route that accepts cookie-based authentication
 *
 * CSRF is NOT required for:
 * - Device endpoints using Bearer token auth (auth:device)
 * - Printer endpoints using device/printer tokens
 * - Guest bootstrap endpoints (before auth exists)
 *
 * AUDIT: See routes/api.php for route definitions and middleware assignments.
 * When adding new endpoints, explicitly list them here - do NOT use wildcards.
 */
class ApiCsrfExemption extends ValidateCsrfToken
{
    /**
     * Stateless API endpoints exempt from CSRF protection.
     *
     * These endpoints use Bearer token or printer token authentication (stateless)
     * rather than session cookies. The tablet-ordering-pwa and woosoo-print-bridge
     * use Bearer tokens for all device API calls.
     *
     * KEEP CSRF ENABLED FOR:
     * - api/v2/devices/* (admin endpoints using auth:sanctum, may use sessions)
     * - api/sessions/{id}/reset (admin endpoint using auth:sanctum)
     * - Any future admin UI routes
     */
    protected $except = [
        // ============================================================
        // DEVICE BOOTSTRAP (Guest - before Bearer auth exists)
        // ============================================================
        'api/devices/register',
        'api/devices/login',
        'api/device/lookup-by-ip',

        // ============================================================
        // DEVICE API (auth:device - Bearer token stateless)
        // ============================================================
        // Device management
        'api/devices',                      // GET /devices (index)
        'api/devices/*',                    // GET/PUT/DELETE /devices/{id}
        'api/devices/refresh',
        'api/devices/logout',
        'api/devices/create-order',
        'api/devices/latest-session',

        // Device info
        'api/device/table',
        'api/token/verify',

        // ============================================================
        // DEVICE API (auth:device - Bearer token stateless)
        // ============================================================
        // Device order and session endpoints
        'api/device-order/*',               // GET /device-order/{order}
        'api/device-orders',                // GET /device-orders (index)
        'api/session/latest',
        'api/sessions/current',
        'api/sessions/join',
        'api/devices/latest-session',

        // Table & service endpoints
        'api/tables/services',
        'api/service/request',

        // ============================================================
        // ORDER API (auth:device - Bearer token stateless)
        // ============================================================
        'api/order/*/dispatch',
        'api/order/*/refill',
        'api/order/*/print-refill',
        'api/order/*/printed',
        'api/order/*/print',

        // ============================================================
        // MENU API (Public/guest or auth:device - stateless)
        // ============================================================
        'api/menus',
        'api/menus/*',

        // ============================================================
        // V1 API (auth:device - Bearer token stateless)
        // ============================================================
        'api/v1/orders',
        'api/v1/orders/*',
        'api/v1/orders/status/bulk',

        // ============================================================
        // V2 TABLET API (auth:device - Bearer token stateless)
        // NOTE: Explicitly listing tablet routes, NOT v2/devices/* (admin)
        // ============================================================
        'api/v2/tablet/packages',
        'api/v2/tablet/packages/*',
        'api/v2/tablet/meat-categories',
        'api/v2/tablet/categories',
        'api/v2/tablet/categories/*',

        // ============================================================
        // PRINTER API (Device/printer auth - stateless)
        // NOTE: These routes are gated by PrintEventFeatureFlag middleware
        // ============================================================
        'api/printer/unprinted-events',
        'api/printer/unprinted-orders',
        'api/printer/print-events/*',
        'api/printer/heartbeat',
        'api/print-events/unprinted',
        'api/print-events/*',
        'api/orders/*/printed',
        'api/orders/printed/bulk',

        // ============================================================
        // PUBLIC/MISC (No auth or stateless)
        // ============================================================
        'api/device/ip',
        'api/config',
        'api/deployment-info',
        'api/health',
        'api/token/create',                 // Guest user token creation
    ];

    /**
     * Check if the request path matches an exempt pattern.
     *
     * Override parent to add wildcard support for explicit patterns like 'api/order/*'
     */
    protected function inExceptArray($request): bool
    {
        $path = $request->path();

        foreach ($this->except as $pattern) {
            // Exact match
            if ($path === $pattern) {
                return true;
            }

            // Wildcard match (e.g., 'api/order/*' matches 'api/order/123/refill')
            if (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('*', '.*', $pattern) . '$#';
                if (preg_match($regex, $path)) {
                    return true;
                }
            }
        }

        return false;
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Middleware to exempt only stateless device-bootstrap API endpoints from CSRF.
 *
 * Keep CSRF protection enabled for all other routes, including session-authenticated
 * Sanctum endpoints under /api that rely on browser cookies.
 */
class ApiCsrfExemption extends ValidateCsrfToken
{
    /**
     * Stateless device API endpoints.
     *
     * These endpoints use Bearer token authentication (stateless) rather than
     * session cookies, so they don't require CSRF protection. The tablet-ordering-pwa
     * and woosoo-print-bridge use Bearer tokens for all device API calls.
     */
    protected $except = [
        // Device bootstrap endpoints (before Bearer auth exists)
        'api/devices/register',
        'api/devices/login',
        'api/device/lookup-by-ip',

        // Device API endpoints (stateless Bearer token auth)
        'api/devices/create-order',
        'api/devices/refresh',
        'api/devices/logout',
        'api/device/table',
        'api/token/verify',
        
        // Device order and session endpoints (stateless Bearer token auth)
        'api/device-order/*',
        'api/device-orders',
        'api/sessions/*',
        'api/session/latest',
        'api/service/request',
        'api/tables/services',
        
        // Order endpoints (stateless Bearer token auth)
        'api/order/*/dispatch',
        'api/order/*/refill',
        'api/order/*/print-refill',
        
        // V1 device API endpoints (stateless Bearer token auth)
        'api/v1/orders',
        'api/v1/orders/*',
        
        // V2 tablet API endpoints (stateless Bearer token auth)
        'api/v2/tablet/*',
        
        // Printer API endpoints (stateless Bearer token auth)
        'api/printer/*',
        'api/print-events/*',
        'api/orders/*/printed',
        'api/orders/printed/bulk',
        
        // Public endpoints (no auth required)
        'api/device/ip',
        'api/config',
        'api/deployment-info',
        'api/health',
        'api/menus/*',
    ];
}

<?php

use App\Support\PublicOrigin;

return [
    'url' => env('APP_URL', PublicOrigin::appUrl()),

    'krypton' => [
        'terminal_id' => (int) env('KRYPTON_TERMINAL_ID', 1),
        'tax_rate'    => (float) env('KRYPTON_TAX_RATE', 0.10),
    ],

    /**
     * PrintEvent feature flag.
     *
     * When disabled (default for MVP), Nexus does not execute printer work.
     * woosoo-print-bridge is the sole active print execution path.
     * Enable this only for future printer expansion work.
     */
    'print_events_enabled' => env('NEXUS_PRINT_EVENTS_ENABLED', false),
];
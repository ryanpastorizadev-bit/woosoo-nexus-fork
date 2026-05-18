<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalize legacy OrderStatus values that were removed from the App\Enums\OrderStatus
 * cases: `in_progress`, `ready`, `served`.
 *
 * The enum now only ships CONFIRMED → COMPLETED | VOIDED | CANCELLED transitions.
 * Any DeviceOrder row carrying one of the removed values would crash on hydration
 * (the enum cast invokes OrderStatus::from() which throws ValueError on unknown values).
 *
 * Mapping decision (2026-05-14): all three legacy values become CONFIRMED so the next
 * `pos:sync-payment-statuses` pass reconciles them to COMPLETED or VOIDED from POS truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('device_orders')
            ->whereIn('status', ['in_progress', 'ready', 'served'])
            ->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        // Forward-only. The legacy values no longer exist as enum cases and cannot
        // be re-introduced safely. Leave a no-op to keep rollback semantics clean.
    }
};

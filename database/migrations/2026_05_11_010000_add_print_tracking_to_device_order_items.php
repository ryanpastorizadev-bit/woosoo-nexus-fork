<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_order_items', function (Blueprint $table) {
            $table->boolean('is_printed')->default(false)->index()->after('total');
            $table->timestamp('printed_at')->nullable()->after('is_printed');
            $table->foreignId('printed_by_print_event_id')->nullable()->constrained('print_events')->nullOnDelete()->after('printed_at');
            $table->string('print_type')->nullable()->index()->after('printed_by_print_event_id');
            $table->uuid('client_submission_id')->nullable()->index()->after('print_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_order_items', function (Blueprint $table) {
            $table->dropIndex(['is_printed']);
            $table->dropIndex(['print_type']);
            $table->dropIndex(['client_submission_id']);
            $table->dropForeign(['printed_by_print_event_id']);
            $table->dropColumn(['is_printed', 'printed_at', 'printed_by_print_event_id', 'print_type', 'client_submission_id']);
        });
    }
};

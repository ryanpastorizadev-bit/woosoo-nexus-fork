<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates durable refill submission tracking to prevent duplicate POS inserts.
     * State machine: NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
     */
    public function up(): void
    {
        Schema::create('refill_submissions', function (Blueprint $table) {
            $table->id();
            
            // Scope: device_id + order_id + client_submission_id
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('device_order_id')->constrained('device_orders')->cascadeOnDelete();
            $table->uuid('client_submission_id')->index();
            
            // Unique constraint prevents duplicate submissions
            $table->unique(['device_id', 'device_order_id', 'client_submission_id'], 'unique_refill_submission');
            
            // State machine tracking
            $table->string('status', 30)->default('NEW'); // NEW, PROCESSING, POS_CREATED, MIRRORED, PRINT_EVENT_CREATED, COMPLETED, FAILED
            $table->timestamp('pos_created_at')->nullable();
            $table->timestamp('mirrored_at')->nullable();
            $table->timestamp('print_event_created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Store POS ordered_menu IDs for idempotency verification
            $table->json('pos_ordered_menu_ids')->nullable();
            
            // Store response for replay
            $table->json('cached_response')->nullable();
            
            // Error tracking
            $table->text('last_error')->nullable();
            
            // Lock tracking for concurrent requests
            $table->timestamp('processing_started_at')->nullable();
            $table->string('processing_lock_id', 64)->nullable()->index();
            
            $table->timestamps();
            
            // Index for status queries
            $table->index(['device_order_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refill_submissions');
    }
};

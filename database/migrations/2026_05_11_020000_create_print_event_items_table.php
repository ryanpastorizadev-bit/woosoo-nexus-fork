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
        Schema::create('print_event_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('print_event_id')
                ->constrained('print_events')
                ->cascadeOnDelete();

            $table->foreignId('device_order_item_id')
                ->constrained('device_order_items')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            $table->unique(['print_event_id', 'device_order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_event_items');
    }
};

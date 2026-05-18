<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_descriptions', function (Blueprint $table) {
            $table->id();
            // References Krypton POS menus.id — cross-DB, no FK constraint.
            // One global, package-independent description per modifier item.
            $table->unsignedBigInteger('krypton_menu_id')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_descriptions');
    }
};

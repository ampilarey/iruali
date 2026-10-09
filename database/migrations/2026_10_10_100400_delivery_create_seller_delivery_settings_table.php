<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Seller Centre → Settings → Delivery & pickup: how fast the shop usually ships, and
        // whether customers may collect from the shop (where, and when it is open).
        Schema::create('seller_delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('ships_within_days')->default(1);
            $table->boolean('pickup_enabled')->default(false);
            $table->string('pickup_address', 255)->nullable();
            $table->foreignId('pickup_island_id')->nullable()->constrained('islands')->nullOnDelete();
            $table->string('pickup_hours', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_delivery_settings');
    }
};

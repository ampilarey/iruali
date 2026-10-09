<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin → Delivery: one row per delivery area that has its own fee or transit days.
        // area is "greater_male", "islands" (every island without its own row) or an atoll name
        // as it appears in islands.atoll. A blank fee falls back to the fee in Settings.
        Schema::create('delivery_rates', function (Blueprint $table) {
            $table->id();
            $table->string('area', 100)->unique();
            $table->decimal('fee', 10, 2)->nullable();
            $table->unsignedTinyInteger('transit_days_min')->nullable();
            $table->unsignedTinyInteger('transit_days_max')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rates');
    }
};

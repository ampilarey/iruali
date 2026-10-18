<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Frequently bought together", worked out nightly from orders (insights:compute-pairs)
        Schema::create('product_pairs', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paired_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('together');
            $table->primary(['product_id', 'paired_product_id']);
            $table->index(['product_id', 'together']);
        });

        // How often suggestions are shown and added, per day and place (product page, cart)
        Schema::create('suggestion_stats', function (Blueprint $table) {
            $table->date('day');
            $table->string('place', 20);
            $table->unsignedInteger('shown')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->primary(['day', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_stats');
        Schema::dropIfExists('product_pairs');
    }
};

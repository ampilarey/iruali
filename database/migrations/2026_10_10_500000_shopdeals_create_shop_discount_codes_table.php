<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop discount codes: codes a shop runs on its own items and pays for itself (Seller Centre →
 * Discount codes). A code is unique per shop and matched without regard to case (kept in capitals).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('type', 10)->default('percent'); // percent | fixed (MVR)
            $table->decimal('value', 10, 2);
            $table->decimal('min_spend', 10, 2)->nullable(); // on the shop's eligible items, after multi-buy savings
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable(); // all customers together
            $table->unsignedInteger('max_uses_per_customer')->nullable(); // by account, or by email for guests
            $table->string('applies_to', 10)->default('all'); // all | selected (the products below)
            $table->boolean('is_active')->default(true); // false: paused by the shop
            $table->timestamps();
            $table->unique(['seller_id', 'code']);
        });

        Schema::create('shop_discount_code_product', function (Blueprint $table) {
            $table->foreignId('shop_discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['shop_discount_code_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_discount_code_product');
        Schema::dropIfExists('shop_discount_codes');
    }
};

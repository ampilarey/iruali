<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every order that used a shop discount code: what the code took off and what the shop sold, for
 * the code's use limits (cancelled orders stop counting) and the shop's report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_discount_code_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30); // as the customer used it (the shop may rename the code later)
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable(); // lower case; guests are limited by the email they order with
            $table->decimal('amount', 12, 2); // what the code took off this order
            $table->decimal('sales', 12, 2); // the shop's items on this order, after the shop's discounts
            $table->timestamps();
            $table->index(['shop_discount_code_id', 'user_id']);
            $table->index(['shop_discount_code_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_discount_redemptions');
    }
};

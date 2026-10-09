<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quoted line: the cart line an accepted quote made (its quantity and price are the quote's) and
 * the order item it became. Deleting a quote takes its cart line with it; an order item keeps
 * standing without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('quote_request_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('quote_request_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('quote_request_id'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('quote_request_id'));
    }
};

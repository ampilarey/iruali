<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shop's part of an order holding pre-order items: "Awaiting stock" until every one of them has
 * its stock (it can be prepared but not sent), the latest date they are expected to ship, and when
 * the last of the awaited stock arrived (the shop's days to ship count from then).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->boolean('awaiting_stock')->default(false)->index();
            $table->date('preorder_ship_date')->nullable();
            $table->timestamp('stock_arrived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropIndex(['awaiting_stock']);
            $table->dropColumn(['awaiting_stock', 'preorder_ship_date', 'stock_arrived_at']);
        });
    }
};

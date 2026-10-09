<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where shop-funded discounts are kept on orders:
 * - each order item: its multi-buy saving and its share of the shop's code (line amounts);
 * - each shop's part: shop_discount, everything the shop funds on it (commission and earnings are
 *   worked out on subtotal - shop_discount);
 * - the order: the total of its shops' discounts;
 * - each returned line: what the customer actually paid for the returned units (the refund basis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('multibuy_discount', 10, 2)->default(0);
            $table->decimal('shop_code_discount', 10, 2)->default(0);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('shop_discount', 12, 2)->default(0)->after('subtotal');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shop_discount', 10, 2)->default(0)->after('voucher_discount');
        });

        Schema::table('return_request_items', function (Blueprint $table) {
            $table->decimal('refund_value', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('return_request_items', fn (Blueprint $table) => $table->dropColumn('refund_value'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('shop_discount'));
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn('shop_discount'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['multibuy_discount', 'shop_code_discount']));
    }
};

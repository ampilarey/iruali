<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shops may set their own delivery fees (Seller Centre → Settings → Delivery & pickup): to their
 * home island, to Greater Malé and to other islands, with an optional free-delivery amount. Such a
 * shop delivers its part itself and is paid the fee (and its bulky-item charges) with its earnings.
 * Shops that don't keep iruali's area fees, charged once per order and kept by iruali, as before.
 *
 * Each order part records its fee and who keeps it; the order records the shops' share of its
 * delivery, so iruali's delivery income (and its GST) is the rest. Existing orders: shops' share 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_delivery_settings', function (Blueprint $table) {
            $table->boolean('own_fees')->default(false)->after('pickup_hours');
            $table->decimal('fee_local', 10, 2)->nullable()->after('own_fees');
            $table->decimal('fee_greater_male', 10, 2)->nullable()->after('fee_local');
            $table->decimal('fee_islands', 10, 2)->nullable()->after('fee_greater_male');
            $table->decimal('free_over', 10, 2)->nullable()->after('fee_islands');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_surcharge');
            $table->boolean('delivery_fee_to_shop')->default(false)->after('delivery_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_fee_shops', 10, 2)->default(0)->after('shipping_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_fee_shops'));
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn(['delivery_fee', 'delivery_fee_to_shop']));
        Schema::table('seller_delivery_settings', fn (Blueprint $table) => $table->dropColumn(['own_fees', 'fee_local', 'fee_greater_male', 'fee_islands', 'free_over']));
    }
};

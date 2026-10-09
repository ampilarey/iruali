<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The delivery area the fee was charged for (greater_male, an atoll, or islands);
            // null when everything is picked up. delivery_zone stays for older code and the API.
            $table->string('delivery_area', 100)->nullable()->after('delivery_zone');
            // The part of shipping_amount that is extra charges for bulky items (never waived)
            $table->decimal('delivery_surcharge', 10, 2)->default(0)->after('shipping_amount');
            // The Malé delivery time slot the customer picked (null = any time)
            $table->dateTime('delivery_slot_starts_at')->nullable()->after('delivery_surcharge');
            $table->dateTime('delivery_slot_ends_at')->nullable()->after('delivery_slot_starts_at');
            // Send as a gift: who receives it, a message for the packing slip, and whether the slip hides prices
            $table->boolean('is_gift')->default(false)->after('shipping_phone');
            $table->string('gift_receiver_name', 120)->nullable()->after('is_gift');
            $table->string('gift_receiver_phone', 20)->nullable()->after('gift_receiver_name');
            $table->string('gift_message', 200)->nullable()->after('gift_receiver_phone');
            $table->boolean('gift_hide_prices')->default(false)->after('gift_message');

            $table->index('delivery_slot_starts_at', 'orders_delivery_slot_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_delivery_slot_idx');
            $table->dropColumn([
                'delivery_area', 'delivery_surcharge', 'delivery_slot_starts_at', 'delivery_slot_ends_at',
                'is_gift', 'gift_receiver_name', 'gift_receiver_phone', 'gift_message', 'gift_hide_prices',
            ]);
        });
    }
};

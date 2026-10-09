<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price-drop alerts (php artisan wishlist:price-drops): the product's final price when it was
 * wishlisted, and the last price the customer was told about, so a drop is reported only once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            // Null for rows saved before this: the daily run fills it in with that day's price
            $table->decimal('saved_price', 10, 2)->nullable()->after('product_variant_id');
            $table->decimal('notified_price', 10, 2)->nullable()->after('saved_price');
            $table->timestamp('price_drop_notified_at')->nullable()->after('notified_price');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn(['saved_price', 'notified_price', 'price_drop_notified_at']);
        });
    }
};

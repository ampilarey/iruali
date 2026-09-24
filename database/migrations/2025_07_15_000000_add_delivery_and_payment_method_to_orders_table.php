<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_zone')) {
                $table->string('delivery_zone')->nullable()->after('shipping_country');
            }
            if (! Schema::hasColumn('orders', 'shipping_amount')) {
                $table->decimal('shipping_amount', 10, 2)->default(0)->after('delivery_zone');
            }
            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->default('cod')->after('shipping_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['delivery_zone', 'shipping_amount', 'payment_method'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

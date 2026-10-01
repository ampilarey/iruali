<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            // Courier deliveries: who carries it and how to follow it
            $table->string('courier', 100)->nullable()->after('tracking_note');
            $table->string('tracking_number', 100)->nullable()->after('courier');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
            // Island deliveries: the boat or flight it travels on and when it should land
            $table->string('vessel_or_flight', 150)->nullable()->after('tracking_url');
            $table->date('expected_delivery_date')->nullable()->after('vessel_or_flight');
            // The new fulfilment step between shipped and delivered
            $table->timestamp('out_for_delivery_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn([
            'courier', 'tracking_number', 'tracking_url', 'vessel_or_flight', 'expected_delivery_date', 'out_for_delivery_at',
        ]));
    }
};

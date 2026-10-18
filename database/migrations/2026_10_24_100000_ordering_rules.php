<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shop's minimum order and weekly opening hours (Seller Centre → Delivery & pickup)
        Schema::table('seller_delivery_settings', function (Blueprint $table) {
            $table->decimal('min_order_amount', 10, 2)->nullable()->after('free_over');
            $table->json('opening_hours')->nullable()->after('pickup_hours'); // {"1": ["09:00", "21:00"], ...}, ISO weekday; missing: closed
        });

        // The small-order fee is part of shipping_amount (kept for iruali); this says how much of it
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('small_order_fee', 10, 2)->default(0)->after('shipping_amount');
        });

        // "Tell me when ordering is back" while ordering is paused
        Schema::create('ordering_back_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('locale', 5)->default('en');
            $table->timestamp('notified_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordering_back_requests');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('small_order_fee'));
        Schema::table('seller_delivery_settings', fn (Blueprint $table) => $table->dropColumn(['min_order_amount', 'opening_hours']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One shop's part of an order cancelled on its own (FulfilmentService::cancelPart()): when, the money
 * refunded for it and the redeemed points given back, so a later cancellation of the whole order
 * neither restocks its items nor refunds or returns them a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->timestamp('cancelled_alone_at')->nullable();
            $table->decimal('cancelled_refund', 12, 2)->default(0);
            $table->unsignedInteger('points_returned')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn(['cancelled_alone_at', 'cancelled_refund', 'points_returned']);
        });
    }
};

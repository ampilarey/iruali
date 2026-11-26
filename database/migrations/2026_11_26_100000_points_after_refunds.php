<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points and refunds:
 *  - orders.loyalty_points_taken_back: earned points already taken back for return and dispute
 *    refunds on the order, in proportion to the money refunded (OrderService::takeBackEarnedPoints()),
 *    so a later cancellation takes back only the rest.
 *  - disputes.points_returned: redeemed points a dispute refund gave back (DisputeService::pointsFor()),
 *    counted off what a later return or cancellation gives back.
 *  - carts.points_redeemed: points the customer chose to spend, kept with the cart (as its voucher
 *    code is) so the app's choice reaches the order: the API has no session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points_taken_back')->default(0)->after('loyalty_points_awarded_at');
        });
        Schema::table('disputes', function (Blueprint $table) {
            $table->unsignedInteger('points_returned')->default(0)->after('amount_resolved');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedInteger('points_redeemed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('loyalty_points_taken_back');
        });
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('points_returned');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('points_redeemed');
        });
    }
};

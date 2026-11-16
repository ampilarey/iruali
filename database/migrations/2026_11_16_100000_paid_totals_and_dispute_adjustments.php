<?php

use App\Support\PaidShares;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * order_items.paid_total: what the customer actually paid for the line after the iruali voucher and
 * points (App\Support\PaidShares), so returns and disputes never refund more than was paid.
 * seller_adjustments.dispute_id: the shop deduction a dispute refund made, so it can be reversed
 * when the order is cancelled afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('paid_total', 10, 2)->nullable()->after('price');
        });
        Schema::table('seller_adjustments', function (Blueprint $table) {
            $table->foreignId('dispute_id')->nullable()->after('return_request_id')->constrained('disputes')->nullOnDelete();
        });

        // Orders placed before this: worked out from the voucher and points they kept. Lines of an
        // order with neither paid their net total.
        DB::table('order_items')->update(['paid_total' => DB::raw('ROUND(price * quantity - COALESCE(multibuy_discount, 0) - COALESCE(shop_code_discount, 0), 2)')]);
        \App\Models\Order::withTrashed()
            ->where(fn ($q) => $q->where('voucher_discount', '>', 0)->orWhere('points_redeemed_discount', '>', 0))
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    PaidShares::apply($order);
                }
            });
    }

    public function down(): void
    {
        Schema::table('seller_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispute_id');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('paid_total');
        });
    }
};

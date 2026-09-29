<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Commission iruali keeps from this shop's sales (percent). Null = the default in Settings.
            $table->decimal('commission_rate', 5, 2)->nullable()->after('seller_approved_at');
            // Where iruali pays the shop's earnings
            $table->string('payout_bank_name', 100)->nullable()->after('commission_rate');
            $table->string('payout_account_name', 150)->nullable()->after('payout_bank_name');
            $table->string('payout_account_number', 40)->nullable()->after('payout_account_name');
        });

        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable(); // bank transfer reference
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        // One part per shop in each order: its own status and tracking, and what the shop earns from it.
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('tracking_note', 255)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('seller_earnings', 12, 2)->default(0);
            $table->foreignId('payout_id')->nullable()->constrained('seller_payouts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'seller_id']);
            $table->index(['seller_id', 'status']);
        });

        $this->backfill();
    }

    /**
     * Give orders placed before this change their shop parts, mirroring the order's status.
     */
    protected function backfill(): void
    {
        $default = (float) (DB::table('settings')->where('key', 'default_commission_rate')->value('value') ?? 10);
        $rates = DB::table('users')->whereNotNull('commission_rate')->pluck('commission_rate', 'id');

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('order_items.order_id, products.seller_id, orders.status, orders.updated_at, sum(order_items.price * order_items.quantity) as subtotal')
            ->groupBy('order_items.order_id', 'products.seller_id', 'orders.status', 'orders.updated_at')
            ->get();

        foreach ($rows as $row) {
            $rate = $row->seller_id ? (float) ($rates[$row->seller_id] ?? $default) : 0.0;
            $subtotal = round((float) $row->subtotal, 2);
            $commission = round($subtotal * $rate / 100, 2);
            DB::table('seller_orders')->insert([
                'order_id' => $row->order_id,
                'seller_id' => $row->seller_id,
                'status' => $row->status,
                'shipped_at' => in_array($row->status, ['shipped', 'delivered'], true) ? $row->updated_at : null,
                'delivered_at' => $row->status === 'delivered' ? $row->updated_at : null,
                'subtotal' => $subtotal,
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'seller_earnings' => $subtotal - $commission,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('seller_payouts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['commission_rate', 'payout_bank_name', 'payout_account_name', 'payout_account_number']));
    }
};

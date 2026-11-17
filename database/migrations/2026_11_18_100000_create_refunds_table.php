<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per refund owed on an order (PaymentService::flagRefund()). The order's refund_* columns
 * stay as the running summary of the refund now due or last sent; a second refund on the same order
 * used to write over the first there, leaving it only in the audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('due'); // due | refunded
            $table->string('method', 20)->nullable();     // card | bank | wallet, once sent
            $table->string('reference', 100)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        // Refunds already on orders: one row each, from the summary columns
        DB::table('orders')->whereIn('refund_status', ['due', 'refunded'])->where('refund_amount', '>', 0)->orderBy('id')
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    $wallet = str_starts_with((string) $order->refund_reference, 'WALLET-');
                    DB::table('refunds')->insert([
                        'order_id' => $order->id,
                        'amount' => $order->refund_amount,
                        'reason' => $order->refund_reason,
                        'status' => $order->refund_status,
                        'method' => $order->refund_status === 'refunded' ? ($wallet ? 'wallet' : ($order->payment_method === 'bml' ? 'card' : 'bank')) : null,
                        'reference' => $order->refund_reference,
                        'approved_by' => $order->refund_approved_by,
                        'approved_at' => $order->refund_approved_at,
                        'refunded_at' => $order->refunded_at,
                        'created_at' => $order->refunded_at ?? $order->updated_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

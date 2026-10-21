<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-orders with a booking fee (App\Services\BookingFeeService), second round:
 *
 * - Days per product instead of a fixed 7: how long the customer has to pay the balance once the
 *   item arrives (products.preorder_balance_days), and how late it may be before the customer gets
 *   everything back (products.preorder_late_days; for any pre-order it is also when Admin → Inbox
 *   lists it as late). Each order keeps the days it was placed with.
 * - GST on the booking fee: the shop's tax invoice (or receipt) for the fee when it is paid, from
 *   the shop's own invoice series; the invoice for the rest when the balance is paid; a refunded
 *   fee reversed in the GST report, a kept fee left as a sale (iruali's commission on it counted
 *   when it is kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('preorder_balance_days')->nullable()->after('preorder_deposit_value');
            $table->unsignedSmallInteger('preorder_late_days')->nullable()->after('preorder_balance_days');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Days past its date after which the line is late, as when the order was placed
            $table->unsignedSmallInteger('preorder_late_days')->nullable()->after('preorder_late_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Days to pay the balance once the stock is in, as when the order was placed
            $table->unsignedSmallInteger('balance_days')->nullable()->after('balance_due_on');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable(); // the booking fee paid for this shop's part, GST included
            $table->decimal('deposit_gst', 12, 2)->nullable();
            $table->unsignedInteger('deposit_invoice_sequence')->nullable(); // from the shop's own series, like invoice_sequence
            $table->string('deposit_invoice_number', 40)->nullable();
            $table->timestamp('deposit_invoiced_at')->nullable();
            $table->timestamp('deposit_reversed_at')->nullable(); // the fee was refunded when the order was cancelled
            $table->timestamp('deposit_kept_at')->nullable(); // the fee was kept when the order was cancelled
            $table->decimal('deposit_commission', 12, 2)->nullable(); // iruali's commission on a kept fee
            $table->decimal('deposit_commission_gst', 12, 2)->nullable();
            $table->unique('deposit_invoice_number', 'seller_orders_deposit_invoice_number_unique');
            $table->index('deposit_invoiced_at');
            $table->index('deposit_reversed_at');
            $table->index('deposit_kept_at');
        });

        Schema::table('seller_adjustments', function (Blueprint $table) {
            // A booking fee kept for the shop: its commission line on iruali's commission invoice
            $table->foreignId('seller_order_id')->nullable()->after('return_request_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seller_adjustments', fn (Blueprint $table) => $table->dropConstrainedForeignId('seller_order_id'));
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropUnique('seller_orders_deposit_invoice_number_unique');
            $table->dropIndex(['deposit_invoiced_at']);
            $table->dropIndex(['deposit_reversed_at']);
            $table->dropIndex(['deposit_kept_at']);
            $table->dropColumn([
                'deposit_amount', 'deposit_gst', 'deposit_invoice_sequence', 'deposit_invoice_number', 'deposit_invoiced_at',
                'deposit_reversed_at', 'deposit_kept_at', 'deposit_commission', 'deposit_commission_gst',
            ]);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('balance_days'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('preorder_late_days'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['preorder_balance_days', 'preorder_late_days']));
    }
};

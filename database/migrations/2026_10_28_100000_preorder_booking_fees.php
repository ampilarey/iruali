<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-orders with a booking fee (App\Services\BookingFeeService), for goods the shop buys only
 * once a customer orders them. The product form takes a booking fee (a percentage of the price or
 * a fixed amount per unit) and, instead of a fixed date, "ships about N days after the order".
 *
 * The order keeps the booking fee asked at checkout (deposit_amount): the customer pays it first
 * (payment_status "deposit_paid"), then the balance when the stock arrives, before delivery. The
 * fee is kept when the customer cancels, unless the shop moved the date later or is late.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('preorder_lead_days')->nullable()->after('preorder_ship_date');
            $table->string('preorder_deposit_type', 10)->nullable()->after('preorder_lead_days'); // percent or fixed
            $table->decimal('preorder_deposit_value', 10, 2)->nullable()->after('preorder_deposit_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('wallet_refunded_at');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_amount');
            // The bank transfer slip for the booking fee, kept when the slip for the balance comes
            $table->string('deposit_slip_path')->nullable()->after('deposit_paid_at');
            // The fee became refundable: the shop moved the date later, or the order is late
            $table->timestamp('deposit_refundable_at')->nullable()->after('deposit_slip_path');
            // What was kept of the fee when the order was cancelled
            $table->decimal('deposit_kept_amount', 10, 2)->nullable()->after('deposit_refundable_at');
            $table->timestamp('balance_requested_at')->nullable()->after('deposit_kept_amount');
            $table->date('balance_due_on')->nullable()->after('balance_requested_at');
            $table->timestamp('balance_reminded_at')->nullable()->after('balance_due_on');
            $table->timestamp('balance_overdue_at')->nullable()->after('balance_reminded_at');
            $table->index(['payment_status', 'balance_due_on']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            // deposit or balance on a booking-fee order; null pays the whole order
            $table->string('purpose', 10)->nullable()->after('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->dropColumn('purpose'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'balance_due_on']);
            $table->dropColumn([
                'deposit_amount', 'deposit_paid_at', 'deposit_slip_path', 'deposit_refundable_at', 'deposit_kept_amount',
                'balance_requested_at', 'balance_due_on', 'balance_reminded_at', 'balance_overdue_at',
            ]);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['preorder_lead_days', 'preorder_deposit_type', 'preorder_deposit_value']));
    }
};

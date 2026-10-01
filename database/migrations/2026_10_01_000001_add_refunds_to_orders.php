<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money owed back to a customer outside the returns flow: a card payment that landed after the
 * order was cancelled, a paid order that was cancelled, or a duplicate payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('refund_status', 20)->nullable()->after('paid_at'); // due | refunded
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_status');
            $table->string('refund_reason', 100)->nullable()->after('refund_amount');
            $table->string('refund_reference', 100)->nullable()->after('refund_reason');
            $table->timestamp('refunded_at')->nullable()->after('refund_reference');
            $table->index('refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['refund_status', 'refund_amount', 'refund_reason', 'refund_reference', 'refunded_at']);
        });
    }
};

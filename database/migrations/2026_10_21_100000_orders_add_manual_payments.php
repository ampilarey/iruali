<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank transfer and cash on delivery (App\Services\ManualPaymentService), for when card payment is
 * off: the customer's transfer slip, and who confirmed the payment with what reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_slip_path')->nullable()->after('payment_status');
            $table->timestamp('payment_slip_uploaded_at')->nullable()->after('payment_slip_path');
            $table->string('payment_reference', 100)->nullable()->after('payment_slip_uploaded_at');
            $table->foreignId('payment_confirmed_by')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
            $table->index(['payment_method', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_method', 'payment_status']);
            $table->dropConstrainedForeignId('payment_confirmed_by');
            $table->dropColumn(['payment_slip_path', 'payment_slip_uploaded_at', 'payment_reference']);
        });
    }
};

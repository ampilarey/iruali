<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_status')) {
                // unpaid | submitted (slip uploaded) | paid | rejected
                $table->string('payment_status')->default('unpaid')->after('payment_method');
            }
            if (! Schema::hasColumn('orders', 'payment_slip')) {
                $table->string('payment_slip')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_slip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['payment_status', 'payment_slip', 'paid_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

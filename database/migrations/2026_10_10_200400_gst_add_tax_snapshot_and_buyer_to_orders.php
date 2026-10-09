<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * iruali's own GST on the order as it was when it was placed (registered, TIN, rate, the GST in
     * the delivery fee), and the business details a buyer asked to have on the invoices.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('gst_platform_registered')->default(false);
            $table->string('gst_platform_tin', 20)->nullable();
            $table->decimal('gst_rate', 5, 2)->nullable();
            $table->decimal('delivery_gst', 12, 2)->default(0);
            $table->timestamp('gst_captured_at')->nullable();
            $table->string('buyer_business_name', 150)->nullable();
            $table->string('buyer_tin', 20)->nullable();
            $table->string('buyer_business_address', 300)->nullable();
            $table->index('paid_at', 'orders_paid_at_gst_idx'); // the monthly GST report
        });

        // Orders placed before this existed: iruali was not GST-registered then.
        DB::table('orders')->update(['gst_captured_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_paid_at_gst_idx'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'gst_platform_registered', 'gst_platform_tin', 'gst_rate', 'delivery_gst', 'gst_captured_at',
            'buyer_business_name', 'buyer_tin', 'buyer_business_address',
        ]));
    }
};

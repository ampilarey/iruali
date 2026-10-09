<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each shop part keeps its GST as it was when the order was placed (App\Services\GstService):
     * whether the shop was GST-registered, its TIN, name and address, the rate, the GST in its
     * goods and in iruali's commission. Paid parts get the shop's invoice number.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->boolean('gst_registered')->default(false);
            $table->string('gst_tin', 20)->nullable();
            $table->string('gst_business_name', 150)->nullable();
            $table->string('gst_business_address', 300)->nullable();
            $table->decimal('gst_rate', 5, 2)->nullable();
            $table->decimal('gst_taxable', 12, 2)->nullable(); // goods total after any shop discount, GST included
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('commission_gst', 12, 2)->default(0); // GST in iruali's commission (when iruali was registered)
            $table->timestamp('gst_captured_at')->nullable();
            $table->string('invoice_number', 40)->nullable();
            $table->unsignedInteger('invoice_sequence')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('gst_reversed_at')->nullable(); // the paid order was cancelled: its sale is reversed
            $table->unique('invoice_number', 'seller_orders_invoice_number_unique');
            $table->index(['gst_registered', 'invoiced_at'], 'seller_orders_gst_idx');
        });

        // Parts placed before this existed: nobody was GST-registered then, so freeze them as such.
        DB::table('seller_orders')->update(['gst_captured_at' => now(), 'gst_taxable' => DB::raw('subtotal')]);
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropUnique('seller_orders_invoice_number_unique');
            $table->dropIndex('seller_orders_gst_idx');
        });
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn([
            'gst_registered', 'gst_tin', 'gst_business_name', 'gst_business_address', 'gst_rate', 'gst_taxable', 'gst_amount',
            'commission_gst', 'gst_captured_at', 'invoice_number', 'invoice_sequence', 'invoiced_at', 'gst_reversed_at',
        ]));
    }
};

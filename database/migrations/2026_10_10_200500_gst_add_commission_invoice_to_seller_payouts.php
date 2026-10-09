<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * iruali's invoice to a shop for the commission in a payout: numbered when the payout is paid,
     * with the shop's tax details as they were on that day.
     */
    public function up(): void
    {
        Schema::table('seller_payouts', function (Blueprint $table) {
            $table->string('invoice_number', 40)->nullable();
            $table->unsignedInteger('invoice_sequence')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->json('invoice_details')->nullable();
            $table->unique('invoice_number', 'seller_payouts_invoice_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('seller_payouts', fn (Blueprint $table) => $table->dropUnique('seller_payouts_invoice_number_unique'));
        Schema::table('seller_payouts', fn (Blueprint $table) => $table->dropColumn(['invoice_number', 'invoice_sequence', 'invoiced_at', 'invoice_details']));
    }
};

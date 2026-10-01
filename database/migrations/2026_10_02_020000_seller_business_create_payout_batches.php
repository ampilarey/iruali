<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payout batches: several shops paid in one bank bulk transfer. A batch is drafted (one pending
     * payout per shop), exported as a bank file, and marked paid once the bank has processed it.
     */
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique(); // PB-2026-0001
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('draft'); // draft | exported | paid | cancelled
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('bank_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('seller_payouts', function (Blueprint $table) {
            // paid: money sent (the only state before batches existed); pending: waiting in a batch
            $table->string('status', 10)->default('paid')->after('amount');
            $table->foreignId('payout_batch_id')->nullable()->after('created_by')->constrained('payout_batches')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seller_payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_batch_id');
            $table->dropColumn('status');
        });
        Schema::dropIfExists('payout_batches');
    }
};

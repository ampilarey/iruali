<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin → Settlements: money-in lines from an imported BML statement, matched to orders
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique(); // the same line imported twice is skipped
            $table->string('batch', 40)->index();
            $table->date('posted_on');
            $table->string('description', 255);
            $table->string('reference', 120)->nullable();
            $table->decimal('amount', 12, 2);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_kind', 20)->nullable(); // card, transfer, amount, manual
            $table->string('status', 20)->default('unmatched')->index(); // matched, unmatched, ignored
            $table->timestamps();
        });

        // GST returns filed (Admin → Tax): the month's figures frozen when it was locked
        Schema::create('gst_periods', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique(); // 2026-10
            $table->json('totals');
            $table->string('filing_reference', 60)->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_periods');
        Schema::dropIfExists('bank_statement_lines');
    }
};

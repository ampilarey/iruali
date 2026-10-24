<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which version of the Seller Terms each shop agreed to, and when (App\Support\SellerTerms). The
 * shop's current agreement sits on users; every agreement is also kept as a row, with who pressed
 * "I agree" (the owner), from where, so iruali can show what a shop accepted at any time. A shop that
 * has not agreed to the current version can't use the Seller Centre until it does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('seller_terms_version', 40)->nullable()->after('seller_applied_at');
            $table->timestamp('seller_terms_agreed_at')->nullable()->after('seller_terms_version');
        });

        Schema::create('seller_terms_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agreed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('version', 40);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_terms_agreements');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['seller_terms_version', 'seller_terms_agreed_at']));
    }
};

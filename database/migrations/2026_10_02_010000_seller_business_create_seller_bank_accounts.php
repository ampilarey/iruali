<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where iruali pays each shop. One account per shop; "verified" is ticked by an admin once the
     * account has been checked (for example against a bank statement or a first small transfer).
     */
    public function up(): void
    {
        Schema::create('seller_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('bank', 10); // bml | mib | other
            $table->string('bank_name_other', 100)->nullable();
            $table->string('account_name', 150);
            $table->string('account_number', 40);
            $table->string('currency', 3)->default('MVR');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_bank_accounts');
    }
};

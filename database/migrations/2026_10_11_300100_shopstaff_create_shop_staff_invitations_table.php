<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations to join a shop's staff: emailed as a signed link that works for 7 days. Only a hash of
 * the link's token is kept, so the table alone cannot be used to accept one. An invitation is pending
 * until it is accepted, withdrawn by the owner (revoked_at) or runs out (expires_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->string('token_hash', 64)->unique(); // sha256 of the token in the link
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_staff_invitations');
    }
};

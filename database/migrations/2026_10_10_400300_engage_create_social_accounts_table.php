<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign in with Google, Facebook and Apple (docs/SOCIAL_LOGIN.md): which provider account (its
 * stable "subject" id) belongs to which customer, and whether the customer has a password of
 * their own (accounts made by a social sign-in do not, until they set one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // google, facebook, apple
            $table->string('subject', 191); // the provider's id for the person, never reused
            $table->string('email')->nullable(); // what the provider said last time, for the profile page
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'subject']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_password')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_password');
        });
        Schema::dropIfExists('social_accounts');
    }
};

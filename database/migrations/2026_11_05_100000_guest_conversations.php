<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages from visitors without an account ("Message iruali" and "Ask about this item" without signing in,
 * MessagingService): the conversation keeps the visitor's name and email and/or mobile number, and a secret
 * token for their private link (messages.guest.show) where they read replies and answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('guest_name', 100)->nullable()->after('seller_id');
            $table->string('guest_email', 190)->nullable()->after('guest_name');
            $table->string('guest_phone', 30)->nullable()->after('guest_email');
            $table->string('access_token', 64)->nullable()->unique()->after('guest_phone');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['access_token']);
            $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'access_token']);
        });
    }
};

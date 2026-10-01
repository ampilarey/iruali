<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                // {"customer": {"order_updates": "email|sms|both", ...}} — other roles add their own top-level key
                $table->json('notification_preferences')->nullable()->after('preferred_language');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_preferences'));
        }
    }
};

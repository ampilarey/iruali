<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which emails a shop wants (new order, return, payout, low stock). Null = all of them.
     */
    public function up(): void
    {
        // The SMS batch adds the same column (guarded); whichever runs first creates it.
        if (Schema::hasColumn('users', 'notification_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('onboarding_completed_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_preferences'));
        }
    }
};

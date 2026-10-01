<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty points and referral rewards are now given when an order is paid, not when it is placed.
 * These columns record that it happened so it can't happen twice and can be undone exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('loyalty_points_awarded_at')->nullable()->after('loyalty_points_earned');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by');
            // Two-step sign-in recovery codes were never given a column; they are stored hashed and encrypted.
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
        });

        // Orders placed before this change had their points credited at creation.
        DB::table('orders')->whereNull('loyalty_points_awarded_at')->update(['loyalty_points_awarded_at' => DB::raw('created_at')]);
        // Referred users who already ordered were rewarded under the old rule.
        DB::table('users')->whereNotNull('referred_by')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('orders')->whereColumn('orders.user_id', 'users.id'))
            ->update(['referral_rewarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('loyalty_points_awarded_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['referral_rewarded_at', 'two_factor_recovery_codes']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A ledger of every loyalty-point movement (users.loyalty_points stays the running balance and must
 * always equal the sum of a user's rows). Existing balances get one opening-balance row each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('points'); // + earned, - spent
            $table->string('type', 20); // earned | redeemed | referral | refund | adjustment | expired
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('points_expiry_reminded_at')->nullable()->after('marketing_opt_out_at');
        });

        $now = now();
        DB::table('users')->whereNotNull('loyalty_points')->where('loyalty_points', '!=', 0)
            ->orderBy('id')->select(['id', 'loyalty_points', 'created_at'])
            ->chunk(500, function ($users) use ($now) {
                DB::table('points_transactions')->insert($users->map(fn ($u) => [
                    'user_id' => $u->id,
                    'points' => (int) $u->loyalty_points,
                    'type' => 'adjustment',
                    'order_id' => null,
                    'note' => 'Opening balance',
                    'created_at' => $u->created_at ?? $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('points_expiry_reminded_at'));
        Schema::dropIfExists('points_transactions');
    }
};

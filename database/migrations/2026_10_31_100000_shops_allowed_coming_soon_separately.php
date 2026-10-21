<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-orders and "coming soon" are allowed to a shop separately (users.preorders_allowed and
 * users.coming_soon_allowed, Admin → Sellers → the shop's page): a shop sees only what it is allowed.
 * A shop already allowed keeps both, as the one switch gave it. Products of shops not allowed one of
 * them have it switched off, so no shop lists what it may not (their pre-order settings are kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('coming_soon_allowed')->default(false)->after('preorders_allowed_by');
            $table->timestamp('coming_soon_allowed_at')->nullable()->after('coming_soon_allowed');
            $table->foreignId('coming_soon_allowed_by')->nullable()->after('coming_soon_allowed_at')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->where('preorders_allowed', true)->update([
            'coming_soon_allowed' => true,
            'coming_soon_allowed_at' => DB::raw('preorders_allowed_at'),
            'coming_soon_allowed_by' => DB::raw('preorders_allowed_by'),
        ]);

        $allowed = fn (string $column) => DB::table('users')->select('id')->where($column, true);
        DB::table('products')->where('preorder_enabled', true)->whereNotIn('seller_id', $allowed('preorders_allowed'))
            ->update(['preorder_enabled' => false]);
        DB::table('products')->where('coming_soon', true)->whereNotIn('seller_id', $allowed('coming_soon_allowed'))
            ->update(['coming_soon' => false, 'available_on' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coming_soon_allowed_by');
            $table->dropColumn(['coming_soon_allowed', 'coming_soon_allowed_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Approval is approved_at. Products that were already on sale before that became
 * the only signal would otherwise look unapproved to bulk and CSV tools.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('is_active', true)
            ->whereNull('approved_at')
            ->update(['approved_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Backfilled rows cannot be told apart from products an admin approved.
    }
};

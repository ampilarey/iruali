<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an admin approved the listing. A shop may switch an approved product off and on again
     * (bulk edit, CSV import) without going through approval a second time; a product that was
     * never approved stays pending whatever the shop sets.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('is_active');
        });

        DB::table('products')->where('is_active', true)->whereNull('approved_at')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};

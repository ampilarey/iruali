<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * API clients have no session, so a voucher they apply is stored on the cart.
     */
    public function up(): void
    {
        if (Schema::hasColumn('carts', 'voucher_code')) {
            return;
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('carts', 'voucher_code')) {
            return;
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('voucher_code');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The smallest quantity a customer can ask a bulk quote for, set by the shop on the product form
 * (empty: the default, QuoteService::DEFAULT_MIN_QUANTITY).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('quote_min_quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('quote_min_quantity'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-buy offers ("buy 2, save 5%"): up to three quantity tiers, set on the shop's product form.
 * An offer with a name is a mix-and-match group: every product in it counts towards the tiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multibuy_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80)->nullable(); // set for a mix-and-match group
            $table->json('tiers'); // [{"min_qty": 2, "percent": 5}, ...], smallest quantity first
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('multibuy_offer_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('multibuy_offer_id');
        });
        Schema::dropIfExists('multibuy_offers');
    }
};

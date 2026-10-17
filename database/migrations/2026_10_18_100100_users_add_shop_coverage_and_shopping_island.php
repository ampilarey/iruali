<?php

use App\Support\IslandList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a shop delivers (App\Services\ShopCoverage): its home island and one of three coverage
 * types (all islands; home island and Greater Malé; home island only), plus extra islands and
 * "my whole atoll" for the last two. Existing shops keep "all islands", today's behaviour, unless
 * they had unticked "I ship to other islands": those become "home island and Greater Malé" when
 * their typed island is found in the list.
 *
 * Customers: the island they shop for ("Delivering to") and how listings are shown for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('home_island_id')->nullable()->after('state')->constrained('islands')->nullOnDelete();
            $table->string('delivery_coverage', 20)->default('all')->after('ships_to_islands');
            $table->boolean('delivers_to_home_atoll')->default(false)->after('delivery_coverage');
            $table->foreignId('shopping_island_id')->nullable()->after('home_island_id')->constrained('islands')->nullOnDelete();
            $table->string('shopping_view', 10)->nullable()->after('shopping_island_id');
        });

        Schema::create('shop_delivery_islands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('island_id')->constrained('islands')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['seller_id', 'island_id']);
            $table->index('island_id');
        });

        // Existing shops: their typed island (city) and atoll (state) to a home island
        IslandList::backfillShopHomes();
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_delivery_islands');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_island_id');
            $table->dropConstrainedForeignId('shopping_island_id');
            $table->dropColumn(['delivery_coverage', 'delivers_to_home_atoll', 'shopping_view']);
        });
    }
};

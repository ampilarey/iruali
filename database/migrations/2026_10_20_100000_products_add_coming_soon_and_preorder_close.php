<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Coming soon" (App\Services\ComingSoonService): a product listed before it goes on sale, with an
 * optional launch day. It can't be bought until then; shoppers can ask to be emailed when it goes
 * on sale. Pre-orders get an optional last day for taking them (preorder_closes_on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('coming_soon')->default(false)->after('is_active');
            $table->date('available_on')->nullable()->after('coming_soon');
            $table->date('preorder_closes_on')->nullable()->after('preorder_ship_date');
            $table->index(['coming_soon', 'available_on']);
        });

        Schema::table('stock_alerts', function (Blueprint $table) {
            // "Email me when it goes on sale" (a coming-soon product), not "back in stock"
            $table->boolean('for_launch')->default(false)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('stock_alerts', fn (Blueprint $table) => $table->dropColumn('for_launch'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['coming_soon', 'available_on']);
            $table->dropColumn(['coming_soon', 'available_on', 'preorder_closes_on']);
        });
    }
};

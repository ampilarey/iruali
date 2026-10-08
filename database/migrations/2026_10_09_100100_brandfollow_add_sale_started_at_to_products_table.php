<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a product's current markdown began (compare price above the price, on a product shoppers
 * can see), so the brand followers' digest can tell what went on sale since it last wrote. The
 * Product model keeps it up to date on every save (Product::trackSaleStart).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('sale_started_at')->nullable()->after('compare_price');
            $table->index(['brand_id', 'sale_started_at']);
        });

        // Markdowns shoppers can already see: as of the product's last change (older than any follow)
        DB::table('products')->where('is_active', true)->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price')
            ->update(['sale_started_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'sale_started_at']);
            $table->dropColumn('sale_started_at');
        });
    }
};

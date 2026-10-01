<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Null means "the product's price plus price_adjustment" (how old rows were priced)
            $table->decimal('price', 10, 2)->nullable()->after('price_adjustment');
            $table->json('attributes')->nullable()->after('type'); // {"Size":"M","Colour":"Blue"}
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock_quantity');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false)->after('stock_quantity');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_name')->nullable()->after('product_variant_id');
            $table->string('variant_sku', 100)->nullable()->after('variant_name');
        });

        Schema::table('stock_alerts', function (Blueprint $table) {
            $table->index('product_id');
            $table->dropUnique(['product_id', 'email']);
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unique(['product_id', 'product_variant_id', 'email'], 'stock_alerts_product_variant_email_unique');
        });

        Schema::table('saved_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });
        Schema::table('saved_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });
        Schema::table('stock_alerts', function (Blueprint $table) {
            $table->dropUnique('stock_alerts_product_variant_email_unique');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->unique(['product_id', 'email']);
            $table->dropIndex(['product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['variant_name', 'variant_sku']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_variants');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['price', 'attributes', 'sort_order', 'low_stock_threshold']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the storefront and admin filter and sort on every day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_idx');
            $table->index('payment_status', 'orders_payment_status_idx');
            $table->index(['user_id', 'created_at'], 'orders_user_created_idx');
            $table->index(['payment_method', 'payment_status', 'status', 'created_at'], 'orders_unpaid_sweep_idx');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->index(['session_id', 'status'], 'carts_session_status_idx');
            $table->index(['user_id', 'status'], 'carts_user_status_idx');
            $table->index('updated_at', 'carts_updated_idx');
        });
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->index(['product_id', 'is_approved'], 'reviews_product_approved_idx');
        });
        Schema::table('product_images', function (Blueprint $table) {
            $table->index(['product_id', 'is_main'], 'images_product_main_idx');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->index('brand', 'products_brand_idx');
            $table->index(['seller_id', 'is_active'], 'products_seller_active_idx');
            $table->index('stock_quantity', 'products_stock_idx');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_seller', 'seller_approved'], 'users_seller_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex('orders_status_idx')->dropIndex('orders_payment_status_idx')->dropIndex('orders_user_created_idx')->dropIndex('orders_unpaid_sweep_idx'));
        Schema::table('carts', fn (Blueprint $t) => $t->dropIndex('carts_session_status_idx')->dropIndex('carts_user_status_idx')->dropIndex('carts_updated_idx'));
        Schema::table('product_reviews', fn (Blueprint $t) => $t->dropIndex('reviews_product_approved_idx'));
        Schema::table('product_images', fn (Blueprint $t) => $t->dropIndex('images_product_main_idx'));
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex('products_brand_idx')->dropIndex('products_seller_active_idx')->dropIndex('products_stock_idx'));
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_seller_idx'));
    }
};

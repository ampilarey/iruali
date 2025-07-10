<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Multilingual
            $table->string('name_dv')->nullable()->after('name');
            $table->json('description')->nullable(); // Multilingual
            $table->string('description_dv')->nullable()->after('description');
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->string('sku')->unique();
            $table->integer('stock_quantity')->default(0);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('slug')->unique();
            $table->integer('reorder_point')->default(5)->after('stock_quantity');
            $table->boolean('is_sponsored')->default(false)->after('is_featured');
            $table->timestamp('sponsored_until')->nullable()->after('is_sponsored');
            $table->string('main_image')->nullable()->after('sponsored_until');
            $table->json('images')->nullable()->after('main_image');
            $table->json('tags')->nullable()->after('images');
            $table->string('brand')->nullable()->after('tags');
            $table->string('model')->nullable()->after('brand');
            $table->decimal('weight', 8, 2)->nullable()->after('model');
            $table->string('dimensions')->nullable()->after('weight');
            $table->boolean('requires_shipping')->default(true)->after('dimensions');
            $table->boolean('is_digital')->default(false)->after('requires_shipping');
            $table->string('digital_file')->nullable()->after('is_digital');
            $table->json('wholesale_pricing')->nullable()->after('digital_file');
            $table->string('meta_title')->nullable()->after('wholesale_pricing');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->decimal('sale_price', 10, 2)->nullable()->after('compare_price');
            $table->timestamps();
            $table->index(['is_active', 'is_featured']);
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

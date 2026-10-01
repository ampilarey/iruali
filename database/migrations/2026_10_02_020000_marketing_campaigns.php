<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns (sales and events) with a banner on the home page and a landing page, and the products
 * shops put into them at a discount (admin approves each participation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('type', 10)->default('sale'); // sale | event
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('banner_image')->nullable();
            $table->json('headline'); // {en, dv}
            $table->json('subheadline')->nullable();
            $table->json('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('theme_colour', 7)->default('#0E7C86');
            $table->boolean('is_active')->default(true);
            $table->decimal('discount_percent', 5, 2)->nullable(); // minimum discount shops must give to join
            $table->string('placement', 20)->default('home_hero'); // home_hero | home_strip | category
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->nullable(); // the shop's own discount, else the campaign's
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'product_id']);
            $table->index(['product_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_products');
        Schema::dropIfExists('campaigns');
    }
};

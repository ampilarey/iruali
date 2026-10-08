<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shops iruali has confirmed as authorised sellers of a brand (Admin → Brands → edit). Their
 * products of that brand show an "Authorised seller" badge and they come first on the brand page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_authorised_sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            // The admin who confirmed it (kept as null if that account goes)
            $table->foreignId('authorised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['brand_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_authorised_sellers');
    }
};

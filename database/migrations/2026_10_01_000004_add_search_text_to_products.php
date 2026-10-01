<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One plain, lower-cased column with everything a search can match (both names, brand, model, SKU,
 * the start of the description), kept up to date by the Product model. Searching it is one LIKE on
 * one column instead of five casts of JSON columns per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('search_text')->nullable()->after('description');
        });

        \App\Models\Product::withTrashed()->orderBy('id')->chunkById(200, function ($products) {
            foreach ($products as $product) {
                $product->timestamps = false;
                $product->forceFill(['search_text' => $product->buildSearchText()])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('search_text'));
    }
};

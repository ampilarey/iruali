<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-orders (product form → "Pre-order"): when the product is out of stock, customers can pay now
 * for units coming on a later shipment. The shop gives the date it expects to send them, the most
 * units it will take (counted across the product's variants) and an optional note for shoppers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('preorder_enabled')->default(false);
            $table->date('preorder_ship_date')->nullable();
            $table->unsignedInteger('preorder_limit')->nullable();
            $table->string('preorder_note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['preorder_enabled', 'preorder_ship_date', 'preorder_limit', 'preorder_note']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_phone', 20)->nullable()->after('shipping_country');
            // Most islands have no postal code people know, so it is optional at checkout.
            $table->string('shipping_zip')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_phone');
            $table->string('shipping_zip')->nullable(false)->default('')->change();
        });
    }
};

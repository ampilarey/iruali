<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A brand's name in Thaana, shown to Dhivehi shoppers (Brand::localizedName). products.brand keeps
 * the English name; the Dhivehi one is also matched when shops type it (as a brand_aliases key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('name_dv', 120)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('name_dv');
        });
    }
};

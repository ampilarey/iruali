<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wide cover image across the top of a brand's page (Admin → Brands → Edit), stored on the
 * public disk like the logo, with smaller WebP copies (App\Support\ImageVariants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('banner')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('banner');
        });
    }
};

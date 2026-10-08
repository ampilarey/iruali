<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand campaigns: a campaign may be for one brand only. Shops can then put in only that brand's
 * products, and the campaign shows on the brand's page. Null keeps a campaign open to every product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('placement')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};

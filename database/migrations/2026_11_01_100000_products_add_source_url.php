<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The page abroad a pre-order product is bought from (e.g. a listing on an online shop), so iruali can
 * check what the shop buys. Filled by "Import from a link" (App\Services\ProductImportService) or typed
 * in the product form's Pre-order fieldset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->after('preorder_late_days');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('source_url'));
    }
};

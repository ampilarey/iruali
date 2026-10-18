<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Applies by itself, without a code, when the cart qualifies (App\Support\Promotions)
            $table->boolean('is_automatic')->default(false)->after('is_active');
            // Free delivery while it applies (with or without money off)
            $table->boolean('free_delivery')->default(false)->after('is_automatic');
            // A batch of printed discount cards (single-use codes made together)
            $table->string('batch', 40)->nullable()->index()->after('free_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['batch']);
            $table->dropColumn(['is_automatic', 'free_delivery', 'batch']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop onboarding: logo, banner, delivery options and the moment the checklist was completed.
     * Shops approved before this existed are marked complete so nothing live disappears.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('shop_logo')->nullable()->after('business_description');
            $table->string('shop_banner')->nullable()->after('shop_logo');
            $table->text('delivery_notes')->nullable()->after('shop_banner');
            $table->boolean('ships_to_islands')->default(true)->after('delivery_notes');
            $table->timestamp('onboarding_completed_at')->nullable()->after('ships_to_islands');
        });

        DB::table('users')->where('seller_approved', true)->whereNull('onboarding_completed_at')->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['shop_logo', 'shop_banner', 'delivery_notes', 'ships_to_islands', 'onboarding_completed_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'business_name')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('is_seller');
            $table->text('business_description')->nullable()->after('business_name');
            $table->timestamp('seller_applied_at')->nullable()->after('business_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'business_name')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['business_name', 'business_description', 'seller_applied_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty tiers (the tier a customer was last told about) and birthday offers (the birthday, and
 * the year the last offer went out).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('loyalty_tier', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('birthday_offer_year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['loyalty_tier', 'birth_date', 'birthday_offer_year']);
        });
    }
};

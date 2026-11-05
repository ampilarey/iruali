<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wrong tries on each code (OTP::verify()): after OTP::MAX_ATTEMPTS the code is spent, however many sign-in
 * pages or accounts the guesses came through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('is_used');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};

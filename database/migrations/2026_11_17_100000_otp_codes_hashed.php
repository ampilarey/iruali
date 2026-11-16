<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes are stored as a keyed hash (App\Models\OTP::hashCode()), never as the code. The
 * codes stored before are deleted: each lasted 10 minutes, so at most a few people ask for a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('otps')->delete();
        Schema::table('otps', function (Blueprint $table) {
            $table->string('code', 64)->change();
        });
    }

    public function down(): void
    {
        DB::table('otps')->delete();
        Schema::table('otps', function (Blueprint $table) {
            $table->string('code', 6)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User::ban(), unban() and isBanned() (checked at sign-in) were written against these two
 * columns, but no migration ever created them: banning crashed and the sign-in check never
 * matched anyone. Adding them makes the existing ban check work; nobody is banned by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'banned_until')) {
                $table->timestamp('banned_until')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('users', 'banned_reason')) {
                $table->string('banned_reason', 500)->nullable()->after('banned_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['banned_until', 'banned_reason']);
        });
    }
};

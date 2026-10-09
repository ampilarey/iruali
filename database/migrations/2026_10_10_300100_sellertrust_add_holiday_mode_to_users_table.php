<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holiday mode (Seller Centre → Settings → Holiday mode): the shop's products stay listed but can't
 * be ordered. It ends by itself on the back-on date (holiday_until, a plain date check) or when the
 * shop switches it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('holiday_mode')->default(false);
            $table->date('holiday_until')->nullable();
            $table->string('holiday_message', 500)->nullable();
            $table->timestamp('holiday_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['holiday_mode', 'holiday_until', 'holiday_message', 'holiday_started_at']);
        });
    }
};

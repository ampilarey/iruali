<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The post-deploy smoke test signs in as a dedicated customer (php artisan iruali:smoke --setup)
 * and places then cancels a real order. The flag keeps that account out of analytics, rewards
 * and emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_smoke_test')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_smoke_test')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_smoke_test')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_smoke_test'));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders:alert-late tells the shop and the team once per part
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->timestamp('late_start_alerted_at')->nullable();
            $table->timestamp('overdue_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn(['late_start_alerted_at', 'overdue_alerted_at']));
    }
};

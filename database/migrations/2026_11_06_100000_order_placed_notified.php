<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the customer and the shops were told about an order (OrderNotifier::placedOnce()). A card order is
 * announced only once it is paid, like Bake & Grill: no "order placed" SMS for a payment that never happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('placed_notified_at')->nullable()->after('paid_at');
        });

        // Orders placed before this change were announced when they were placed
        DB::table('orders')->update(['placed_notified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('placed_notified_at');
        });
    }
};

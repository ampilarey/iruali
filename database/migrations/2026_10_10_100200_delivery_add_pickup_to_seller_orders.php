<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            // "deliver" (the shop sends it) or "pickup" (the customer collects it from the shop)
            $table->string('delivery_method', 10)->default('deliver')->after('status');
            // Bulky-item charges the customer paid for this part's delivery (0 for a pickup)
            $table->decimal('delivery_surcharge', 10, 2)->default(0)->after('delivery_method');
            // Where and when to collect, as the shop gave them (copied when the order is placed
            // and again when the shop marks the part ready)
            $table->string('pickup_address', 255)->nullable()->after('delivery_surcharge');
            $table->string('pickup_island', 120)->nullable()->after('pickup_address');
            $table->string('pickup_hours', 500)->nullable()->after('pickup_island');
            // The 6-digit code the customer shows at the shop; wrong tries are counted
            $table->string('pickup_code', 6)->nullable()->after('pickup_hours');
            $table->unsignedTinyInteger('pickup_code_attempts')->default(0)->after('pickup_code');
            $table->timestamp('pickup_ready_at')->nullable()->after('pickup_code_attempts');
            $table->timestamp('pickup_collected_at')->nullable()->after('pickup_ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn([
            'delivery_method', 'delivery_surcharge', 'pickup_address', 'pickup_island', 'pickup_hours',
            'pickup_code', 'pickup_code_attempts', 'pickup_ready_at', 'pickup_collected_at',
        ]));
    }
};

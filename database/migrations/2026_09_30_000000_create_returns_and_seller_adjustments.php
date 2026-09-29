<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // requested → approved → refunded, or rejected
            $table->string('status', 20)->default('requested');
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('photo_path')->nullable(); // private disk
            $table->decimal('items_value', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->boolean('restocked')->default(false);
            $table->text('admin_note')->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        // Money taken back from (negative) or given to (positive) a shop outside its order parts,
        // e.g. an approved return. Settled in the shop's next payout.
        Schema::create('seller_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('seller_payouts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_adjustments');
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
    }
};

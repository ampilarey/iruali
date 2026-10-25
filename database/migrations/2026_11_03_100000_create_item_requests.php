<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Can't find it? We'll bring it for you" (App\Services\ItemRequestService): a customer asks for an
 * item from abroad; an admin sends a price, which creates a pre-order product only that customer can
 * buy (products.item_request_id, never active, so never listed), and accepting puts it in their cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('new')->index();
            // What the customer asks for
            $table->string('link', 500)->nullable();
            $table->string('title', 200);
            $table->text('details')->nullable();
            $table->string('options', 255)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->foreignId('island_id')->nullable()->constrained('islands')->nullOnDelete();
            $table->decimal('budget', 10, 2)->nullable();
            $table->date('needed_by')->nullable();
            $table->string('photo_path')->nullable();
            // The admin's price
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('deposit_percent')->nullable(); // null: the full price at checkout
            $table->unsignedSmallInteger('ships_in_days')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('quote_message')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('declined_reason', 500)->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('item_request_id')->nullable()->after('source_url')->constrained('item_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_request_id');
        });
        Schema::dropIfExists('item_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk quotes: a business customer asks a shop for a price on a quantity of one product, the shop
 * replies with a unit price, the quantity it can supply and how long the price holds (or declines),
 * and the customer accepts it into the cart (QuoteService). The buyer's business details and the
 * product's name are copied in, so the request reads the same later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name'); // English, as it was when asked
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('quantity'); // what the customer asked for
            $table->foreignId('island_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_island', 100);
            $table->string('delivery_atoll', 100)->nullable();
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('business_name', 150);
            $table->string('business_tin', 20)->nullable();
            $table->string('business_address', 300);
            $table->string('status', 20)->default('new'); // new, quoted, accepted, ordered, declined, expired

            // The shop's quote
            $table->decimal('list_price', 10, 2)->nullable(); // the shop's own price when it quoted, for comparison
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedInteger('quoted_quantity')->nullable();
            $table->date('valid_until')->nullable(); // the price holds to the end of this day
            $table->text('shop_message')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->string('declined_by', 10)->nullable(); // shop, customer, admin
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            // The thread: messages the other side has not opened yet
            $table->unsignedSmallInteger('customer_unread')->default(0);
            $table->unsignedSmallInteger('seller_unread')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['customer_id', 'product_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};

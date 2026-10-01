<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One thread per (order, shop) between the customer, the shop and iruali support
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('customer_unread_count')->default(0);
            $table->unsignedInteger('seller_unread_count')->default(0);
            $table->unsignedInteger('admin_unread_count')->default(0);
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamps();
            $table->unique(['order_id', 'seller_id']);
            $table->index(['seller_id', 'seller_unread_count']);
            $table->index(['customer_id', 'customer_unread_count']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 10); // customer | seller | admin
            $table->text('body');
            $table->string('attachment_path')->nullable(); // private disk, images only
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};

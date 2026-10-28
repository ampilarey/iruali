<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The messages centre (App\Services\MessagingService): besides order threads, conversations about an item
 * before buying ("Ask about this item"), with iruali support (customers and shops) and on an item request.
 * Every conversation is kept for good and seen by admins: deleting an order, product, request or user
 * leaves it (and its messages) in place, and a message keeps what was written when contact details were
 * hidden from the other side (original_body, for admins). Messages can be reported, and an admin can stop
 * an account from writing (users.messaging_blocked_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->string('kind', 20)->default('order')->after('id')->index(); // order | product | support | shop_support | item_request
            $table->foreignId('product_id')->nullable()->after('seller_order_id')->constrained('products')->nullOnDelete();
            $table->foreignId('item_request_id')->nullable()->after('product_id')->constrained('item_requests')->nullOnDelete();
            $table->string('subject', 200)->nullable()->after('item_request_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations')->restrictOnDelete();
            $table->text('original_body')->nullable()->after('body'); // as written, when contact details were hidden
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_reason', 500)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('messaging_blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('messaging_blocked_at'));
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reported_by');
            $table->dropColumn(['original_body', 'reported_at', 'report_reason']);
            $table->dropForeign(['conversation_id']);
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_request_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['kind', 'subject']);
        });
    }
};

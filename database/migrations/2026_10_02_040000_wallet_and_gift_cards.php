<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store credit: a wallet per customer (balance on users, every movement in wallet_transactions),
 * gift cards that are redeemed into the wallet, and how much of an order the wallet paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('loyalty_points');
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique()->nullable(); // set when the card is issued (after payment)
            $table->decimal('amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->foreignId('purchaser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // the order that paid for it
            $table->string('recipient_email');
            $table->string('recipient_name', 120)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 12)->default('pending'); // pending (unpaid) | active | redeemed | expired | cancelled
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2); // + credit, - debit (MVR)
            $table->string('type', 20); // refund | gift_card | purchase | adjustment | expired
            $table->string('reference', 100)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gift_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('wallet_amount', 12, 2)->default(0)->after('total_amount'); // part of the total paid from the wallet
            $table->timestamp('wallet_refunded_at')->nullable()->after('wallet_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['wallet_amount', 'wallet_refunded_at']));
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('gift_cards');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('wallet_balance'));
    }
};

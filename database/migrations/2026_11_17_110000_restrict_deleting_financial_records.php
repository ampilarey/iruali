<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders, payments, refunds, payouts and the wallet and points ledgers are records iruali must keep
 * (GST, settlements, disputes). They used to be deleted with the user, order or product they belong
 * to; now the database refuses to delete a user, order or product that still has them (users, orders
 * and products are only ever soft-deleted, and erasing a customer blanks their details instead).
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: string}> table, column, referenced table */
    protected array $keys = [
        ['orders', 'user_id', 'users'],
        ['order_items', 'order_id', 'orders'],
        ['order_items', 'product_id', 'products'],
        ['payment_transactions', 'order_id', 'orders'],
        ['seller_orders', 'order_id', 'orders'],
        ['disputes', 'order_id', 'orders'],
        ['disputes', 'seller_order_id', 'seller_orders'],
        ['return_requests', 'order_id', 'orders'],
        ['return_requests', 'seller_order_id', 'seller_orders'],
        ['wallet_transactions', 'user_id', 'users'],
        ['points_transactions', 'user_id', 'users'],
        ['seller_adjustments', 'seller_id', 'users'],
        ['seller_payouts', 'seller_id', 'users'],
        ['products', 'seller_id', 'users'],
        ['shop_tax_profiles', 'user_id', 'users'],
    ];

    public function up(): void
    {
        $this->rewire(fn ($foreign) => $foreign->restrictOnDelete());
    }

    public function down(): void
    {
        $this->rewire(fn ($foreign) => $foreign->cascadeOnDelete());
    }

    protected function rewire(callable $onDelete): void
    {
        foreach ($this->keys as [$table, $column, $references]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $onDelete) {
                $blueprint->dropForeign([$column]);
                $onDelete($blueprint->foreign($column)->references('id')->on($references));
            });
        }
    }
};

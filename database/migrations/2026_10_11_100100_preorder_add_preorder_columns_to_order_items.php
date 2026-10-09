<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order line bought as a pre-order: the date it was expected to ship (moved with the shop's
 * date), how many of its units the arrived stock has covered so far (oldest orders first), when it
 * was fully covered, and when the daily check found it more than a week late.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_preorder')->default(false);
            $table->date('preorder_ship_date')->nullable();
            $table->unsignedInteger('preorder_allocated_quantity')->default(0);
            $table->timestamp('preorder_allocated_at')->nullable();
            $table->timestamp('preorder_late_at')->nullable();
            // A product's waiting lines: what "Stock arrived" locks, so its locks stay on that product's lines
            $table->index(['product_id', 'is_preorder', 'preorder_allocated_at'], 'order_items_preorder_product_index');
            // The few lines flagged late (the Admin → Inbox count runs on every admin page)
            $table->index('preorder_late_at', 'order_items_preorder_late_index');
        });
    }

    public function down(): void
    {
        // MySQL/MariaDB let the product index above serve the product_id foreign key and dropped the
        // key's own index: give it one again before that index goes
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true) && ! Schema::hasIndex('order_items', 'order_items_product_id_foreign')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->index('product_id', 'order_items_product_id_foreign'));
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_preorder_product_index');
            $table->dropIndex('order_items_preorder_late_index');
            $table->dropColumn(['is_preorder', 'preorder_ship_date', 'preorder_allocated_quantity', 'preorder_allocated_at', 'preorder_late_at']);
        });
    }
};

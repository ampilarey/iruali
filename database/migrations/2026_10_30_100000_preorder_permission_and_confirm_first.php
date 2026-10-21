<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-orders, third round:
 *
 * - Pre-orders and "coming soon" are for the shops an admin allows (users.preorders_allowed, Admin →
 *   Sellers → the shop's page); off for every shop until then.
 * - "Pay once the shop confirms" (App\Services\PreorderConfirmService): the customer orders without
 *   paying, the shop (or an admin) confirms it can get the item, and only then is the customer sent a
 *   link to pay in full. The product says so (products.preorder_confirm_first); the order keeps it,
 *   with its days to pay, when it was confirmed or declined, when payment is due and the reminder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('preorders_allowed')->default(false)->after('auto_approve_listings_by');
            $table->timestamp('preorders_allowed_at')->nullable()->after('preorders_allowed');
            $table->foreignId('preorders_allowed_by')->nullable()->after('preorders_allowed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('preorder_confirm_first')->default(false)->after('preorder_deposit_value');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('confirm_first')->default(false)->after('balance_overdue_at');
            $table->timestamp('availability_confirmed_at')->nullable()->after('confirm_first');
            $table->timestamp('availability_declined_at')->nullable()->after('availability_confirmed_at'); // the shop can't get it: cancelled
            $table->unsignedSmallInteger('pay_days')->nullable()->after('availability_declined_at'); // days to pay once confirmed, as when ordered
            $table->date('pay_due_on')->nullable()->after('pay_days');
            $table->timestamp('pay_reminded_at')->nullable()->after('pay_due_on');
            $table->index(['confirm_first', 'availability_confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['confirm_first', 'availability_confirmed_at']);
            $table->dropColumn(['confirm_first', 'availability_confirmed_at', 'availability_declined_at', 'pay_days', 'pay_due_on', 'pay_reminded_at']);
        });

        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('preorder_confirm_first'));

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preorders_allowed_by');
            $table->dropColumn(['preorders_allowed', 'preorders_allowed_at']);
        });
    }
};

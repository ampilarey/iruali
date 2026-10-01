<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abandoned-cart emails: one row per reminder sent for a cart (stage 1 after 3 hours, stage 2 after
 * 48 hours with an optional voucher), a voucher that only one customer may use, and the customer's
 * opt-out from marketing email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage'); // 1 = first nudge, 2 = second nudge (maybe with a voucher)
            $table->timestamp('sent_at');
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['cart_id', 'stage']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('is_active')->constrained()->nullOnDelete(); // only this customer may use it
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_opt_out_at')->nullable()->after('referral_rewarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('marketing_opt_out_at'));
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('cart_reminders');
    }
};

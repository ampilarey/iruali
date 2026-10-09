<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.staff_require_two_factor: the shop owner asks their staff to use two-step sign-in
 *   before they can open the Seller Centre (Seller Centre → Staff).
 * - product_questions.answered_as_shop: the answer was given for the shop (its owner or one of
 *   its staff) rather than by iruali. Older answers leave it empty and are told apart as before
 *   (answered_by is the shop's owner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('staff_require_two_factor')->default(false);
        });

        Schema::table('product_questions', function (Blueprint $table) {
            $table->boolean('answered_as_shop')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_questions', function (Blueprint $table) {
            $table->dropColumn('answered_as_shop');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_require_two_factor');
        });
    }
};

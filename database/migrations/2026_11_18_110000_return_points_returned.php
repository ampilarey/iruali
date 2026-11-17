<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points the customer spent on items that came back: returned to them when the return is approved
 * (ReturnService::approve()), and counted off what a later cancellation gives back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->unsignedInteger('points_returned')->default(0)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn('points_returned');
        });
    }
};

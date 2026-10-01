<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Guest orders have no account; the guest's details and a random token (for signed links) live on the order.
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_name', 120)->nullable()->after('guest_email');
            $table->string('guest_token', 64)->nullable()->unique()->after('guest_name');
            $table->index('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['guest_email']);
            $table->dropUnique(['guest_token']);
            $table->dropColumn(['guest_email', 'guest_name', 'guest_token']);
        });
    }
};

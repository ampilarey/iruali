<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People who asked to be told when new shop applications reopen (Admin → Sellers → New shop
 * applications). Each gets one email when they do (notified_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 5)->default('en');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_waitlist');
    }
};

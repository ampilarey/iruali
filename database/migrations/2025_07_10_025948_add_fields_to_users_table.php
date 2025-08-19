<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('avatar');
            $table->boolean('is_active')->default(true)->after('status');
            $table->boolean('email_verified')->default(false)->after('status');
            $table->boolean('phone_verified')->default(false)->after('email_verified');
            $table->string('two_factor_secret')->nullable()->after('phone_verified');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->string('referral_code')->unique()->nullable()->after('last_login_ip');
            // Use unsignedBigInteger for SQLite compatibility, skip foreign constraint
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code'); // No FK for SQLite
            $table->integer('loyalty_points')->default(0)->after('referred_by');
            $table->text('address')->nullable()->after('loyalty_points');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('country')->default('Maldives')->after('postal_code');
            $table->date('date_of_birth')->nullable()->after('country');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->boolean('is_seller')->default(false)->after('gender');
            $table->string('preferred_language')->default('en')->after('is_seller');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove foreign key drop for SQLite
            $table->dropColumn([
                'phone', 'avatar', 'status', 'is_active', 'email_verified', 'phone_verified',
                'two_factor_secret', 'two_factor_enabled', 'last_login_at', 'last_login_ip',
                'referral_code', 'referred_by', 'loyalty_points', 'address', 'city',
                'state', 'postal_code', 'country', 'date_of_birth', 'gender', 'is_seller', 'preferred_language'
            ]);
        });
    }
};

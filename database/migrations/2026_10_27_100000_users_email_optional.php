<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Customers who sign up with their mobile number (PhoneLoginController) may have no email
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
    }
};

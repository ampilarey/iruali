<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop staff: people a shop owner lets into the Seller Centre for their shop, with their own sign-in.
 * A shop is its owner's account (users.id). One shop per staff account, hence the unique user_id.
 * The role says which Seller Centre pages they can open (config/shop_staff.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('users')->cascadeOnDelete(); // the owner's account
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('role', 20); // manager | packer
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_staff');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business verification: a shop's registration certificate and number, and its owner's national ID
 * card number and a photo of the card's front, checked by iruali (Admin → Verifications). One row
 * per shop. The files live on the private "local" disk (storage/app/private), never under public/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected
            $table->string('business_registration_number', 50);
            $table->string('certificate_path');
            $table->text('national_id_number'); // encrypted by the model
            $table->string('id_card_path');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_verifications');
    }
};

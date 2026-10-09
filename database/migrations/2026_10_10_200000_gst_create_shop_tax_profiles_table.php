<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shop's GST details (Seller Centre → Settings → Tax). Copied onto each order part when the
     * order is placed, so changing them later never changes an invoice already issued.
     */
    public function up(): void
    {
        Schema::create('shop_tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('gst_registered')->default(false);
            $table->string('tin', 20)->nullable(); // MIRA GST TIN, stored normalised: 1012345GST501
            $table->string('registered_name', 150)->nullable();
            $table->string('business_address', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_tax_profiles');
    }
};

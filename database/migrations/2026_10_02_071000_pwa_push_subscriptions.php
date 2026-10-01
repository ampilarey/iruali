<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Browser push subscriptions ("Get order updates on this device"): one row per browser/device,
     * tied to the signed-in customer. Dropped when the push service says the endpoint is gone.
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500);
            $table->string('public_key', 255)->nullable();
            $table->string('auth_token', 255)->nullable();
            $table->string('content_encoding', 20)->nullable();
            $table->timestamps();

            $table->unique('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};

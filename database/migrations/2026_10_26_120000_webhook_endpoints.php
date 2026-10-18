<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin → Webhooks: other systems told about orders (App\Support\Webhooks)
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('secret', 64);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};

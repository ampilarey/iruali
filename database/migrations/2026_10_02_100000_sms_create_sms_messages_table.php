<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to', 30);
            $table->text('message');
            $table->string('status', 20); // sent | logged | failed | invalid
            $table->text('provider_response')->nullable();
            $table->decimal('cost', 8, 4)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};

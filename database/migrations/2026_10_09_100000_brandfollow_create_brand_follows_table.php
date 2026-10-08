<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers following brands. The daily digest (brands:notify-followers) tells each follower what
 * the brand put on sale since notified_at, or since they followed when nothing was sent yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            // How far the daily digest has covered this follow (null: nothing sent yet)
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_follows');
    }
};

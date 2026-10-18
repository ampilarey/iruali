<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin → Social posts: drafts waiting for approval, then posted to the chosen channels
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('custom'); // product, campaign, custom
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->string('link')->nullable();
            $table->string('image_url')->nullable();
            $table->json('channels'); // facebook, instagram, telegram
            $table->string('status', 20)->default('pending')->index(); // pending, approved, posted, failed, rejected
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('results')->nullable(); // per channel: ok / error
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Customers sharing a product (WhatsApp, Viber, Facebook, Telegram, copy link)
        Schema::create('share_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('network', 20);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_events');
        Schema::dropIfExists('social_posts');
    }
};

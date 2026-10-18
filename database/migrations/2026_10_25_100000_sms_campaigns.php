<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin → SMS campaigns: one message to a group of customers who allow offers by SMS
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('message');
            $table->string('segment', 30); // App\Support\SmsSegments
            $table->string('segment_value', 50)->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, scheduled, sending, sent, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per person a campaign went to, so nobody gets it twice
        Schema::create('sms_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('created_at')->nullable();
            $table->unique(['sms_campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_campaign_recipients');
        Schema::dropIfExists('sms_campaigns');
    }
};

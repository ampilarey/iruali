<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The newsletter sender (Admin → Moderation → Newsletter): issues an admin writes and sends, one
 * delivery row per address and issue (so an address never gets an issue twice), and a confirmed
 * date on footer signups (only confirmed addresses get the newsletter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('locale');
            $table->timestamp('confirmation_sent_at')->nullable()->after('confirmed_at');
        });

        // Signups from before confirmation emails were told they were subscribed: they count as confirmed
        DB::table('newsletter_subscribers')->whereNull('confirmed_at')->update(['confirmed_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        Schema::create('newsletter_issues', function (Blueprint $table) {
            $table->id();
            $table->string('subject_en', 150);
            $table->string('subject_dv', 150)->nullable();
            $table->text('intro_en');
            $table->text('intro_dv')->nullable();
            // What to add after the text: new arrivals (days), deals, a campaign, featured brands
            $table->json('sections')->nullable();
            // What was picked when it was sent (product, campaign and brand ids), so every batch matches
            $table->json('content')->nullable();
            $table->string('status', 20)->default('draft'); // draft, sending, sent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('test_sent_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable(); // when the last email went out
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_issue_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('locale', 5)->default('en');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('newsletter_subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('pending'); // pending, sending, sent, failed, skipped
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['newsletter_issue_id', 'email']);
            $table->index(['newsletter_issue_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
        Schema::dropIfExists('newsletter_issues');
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'confirmation_sent_at']);
        });
    }
};

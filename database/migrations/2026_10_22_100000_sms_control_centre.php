<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS control centre: per-type switches and wordings, and the type, "send after" time (quiet hours)
 * and the reason a message was held back on each logged message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_type_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->boolean('enabled')->default(true);
            $table->text('template_en')->nullable();
            $table->text('template_dv')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->string('type', 40)->nullable()->after('id');
            $table->timestamp('send_after')->nullable()->after('cost');
            $table->index(['type', 'created_at']);
            $table->index(['status', 'send_after']);
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropIndex(['status', 'send_after']);
            $table->dropColumn(['type', 'send_after']);
        });
        Schema::dropIfExists('sms_type_settings');
    }
};

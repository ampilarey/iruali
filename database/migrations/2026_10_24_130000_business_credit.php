<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pay later for approved business buyers (App\Services\CreditService)
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('credit_limit', 12, 2);
            $table->unsignedSmallInteger('terms_days')->default(30);
            $table->string('status', 20)->default('active'); // active, suspended
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // An order paid later: when the invoice is due, and the reminders sent
        Schema::table('orders', function (Blueprint $table) {
            $table->date('credit_due_on')->nullable()->index();
            $table->timestamp('credit_reminded_at')->nullable();
            $table->timestamp('credit_overdue_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['credit_due_on', 'credit_reminded_at', 'credit_overdue_at']));
        Schema::dropIfExists('credit_accounts');
    }
};

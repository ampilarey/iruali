<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per attempt to pay an order online (a customer may try again after a declined card).
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20)->default('bml');
            $table->string('transaction_id')->nullable()->unique();
            $table->string('local_id')->unique();
            $table->unsignedBigInteger('amount'); // laari
            $table->string('currency', 3)->default('MVR');
            $table->string('state', 30)->default('INITIATED');
            $table->text('payment_url')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

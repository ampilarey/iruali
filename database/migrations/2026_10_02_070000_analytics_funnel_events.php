<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-party shopping funnel: one row per step a visitor takes (product viewed, added to cart,
     * checkout opened, order paid). The visitor is a daily-salted hash of the session id, so rows
     * can't be tied back to a person; they are pruned after 90 days.
     */
    public function up(): void
    {
        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_hash', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 20);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['product_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->text('seller_reply')->nullable()->after('helpful_count');
            $table->timestamp('seller_replied_at')->nullable()->after('seller_reply');
            $table->foreignId('seller_reply_user_id')->nullable()->after('seller_replied_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_reply_user_id');
            $table->dropColumn(['seller_reply', 'seller_replied_at']);
        });
        Schema::dropIfExists('review_photos');
    }
};

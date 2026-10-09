<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's optional video (App\Support\ProductVideo): the provider (youtube, youtube_short,
 * tiktok, instagram, instagram_reel, facebook, facebook_reel) and the video's id there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('video_provider', 20)->nullable();
            $table->string('video_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['video_provider', 'video_id']);
        });
    }
};

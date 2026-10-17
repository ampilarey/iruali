<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Edits a shop made to an approved product, waiting for an admin (App\Services\ProductReviewService).
 * The live product keeps showing the approved version until the request is approved. At most one
 * pending request per product: a new edit replaces it. Images uploaded with an edit are stored at
 * once and named in the payload; they are deleted when the request is rejected, replaced or withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | withdrawn
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['product_id', 'status']);
        });

        // Trusted shops: an admin lets a shop's new listings and edits go live without review
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('auto_approve_listings')->default(false);
            $table->timestamp('auto_approve_listings_at')->nullable();
            $table->foreignId('auto_approve_listings_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_approve_listings_by');
            $table->dropColumn(['auto_approve_listings', 'auto_approve_listings_at']);
        });
        Schema::dropIfExists('product_change_requests');
    }
};

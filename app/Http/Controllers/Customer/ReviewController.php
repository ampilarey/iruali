<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Write (or rewrite) the signed-in shopper's review. One review per shopper per product.
     */
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'comment' => 'required|string|min:10|max:2000',
        ]);

        $user = $request->user();

        $verified = $user->orders()
            ->where('status', '!=', 'cancelled')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        ProductReview::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $user->id],
            $data + [
                'reviewer_name' => $user->name,
                'reviewer_email' => $user->email,
                'is_approved' => true,
                'verified_purchase' => $verified,
            ]
        );

        NotificationService::success(__('Thanks! Your review is live.'));

        return redirect(route('products.show', $product).'#reviews');
    }

    public function helpful(Request $request, ProductReview $review)
    {
        $user = $request->user();

        if ($review->user_id !== $user->id) {
            DB::transaction(function () use ($review, $user) {
                $inserted = DB::table('review_votes')->insertOrIgnore([
                    'product_review_id' => $review->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($inserted) {
                    $review->increment('helpful_count');
                }
            });
        }

        return redirect(route('products.show', $review->product).'#review-'.$review->id);
    }
}

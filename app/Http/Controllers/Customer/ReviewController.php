<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\NotificationService;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    use SecureFileUpload;

    public const MAX_PHOTOS = 3;

    /**
     * Write (or rewrite) the signed-in shopper's review. One review per shopper per product.
     * Up to three photos can come with it; on an edit, ticked photos are removed first.
     */
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'comment' => 'required|string|min:10|max:2000',
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer'],
        ], [
            'photos.max' => __('You can add up to :max photos.', ['max' => self::MAX_PHOTOS]),
            'photos.*.max' => __('Each photo must be 4 MB or smaller.'),
            'photos.*.mimes' => __('Photos must be JPG, PNG or WebP.'),
            'photos.*.image' => __('Photos must be JPG, PNG or WebP.'),
        ]);

        $user = $request->user();

        $review = ProductReview::firstOrNew(['product_id' => $product->id, 'user_id' => $user->id]);

        // Room for the new photos once the ones being removed are gone
        $keeping = $review->exists
            ? $review->photos()->whereNotIn('id', $data['remove_photos'] ?? [])->count()
            : 0;
        $incoming = count($request->file('photos', []));
        if ($keeping + $incoming > self::MAX_PHOTOS) {
            return back()->withInput()->withErrors(['photos' => __('You can add up to :max photos.', ['max' => self::MAX_PHOTOS])]);
        }

        $review->fill([
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'],
            'reviewer_name' => $user->name,
            'reviewer_email' => $user->email,
            'verified_purchase' => $this->hasDeliveredOrderWith($user, $product),
        ]);
        // New reviews go live; one an admin hid stays hidden when its author edits it.
        if (! $review->exists) {
            $review->is_approved = true;
        }
        $review->save();

        if ($review->wasRecentlyCreated === false && ! empty($data['remove_photos'])) {
            $review->photos()->whereIn('id', $data['remove_photos'])->get()->each->delete();
        }

        $order = (int) $review->photos()->max('sort_order') + 1;
        foreach ($request->file('photos', []) as $file) {
            $path = $this->storeFileSecurely($file, 'reviews', ['image/jpeg', 'image/png', 'image/webp'], 4096);
            if ($path) {
                $review->photos()->create(['path' => $path, 'sort_order' => $order++]);
            }
        }

        NotificationService::success(__('Thanks! Your review is live.'));

        return redirect(route('products.show', $product).'#reviews');
    }

    /**
     * "Verified purchase" means an order with this product that was actually delivered.
     */
    protected function hasDeliveredOrderWith($user, Product $product): bool
    {
        return $user->orders()
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();
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

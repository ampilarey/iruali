<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Notifications\ReviewReplied;
use App\Support\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Reviews of the shop's products, and the shop's one (editable) reply under each.
 */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $own = fn ($q) => $q->whereHas('product', fn ($p) => $p->withTrashed()->where('seller_id', CurrentShop::id()));

        $reviews = ProductReview::query()->tap($own)
            ->where('is_approved', true)
            ->when($request->query('show') !== 'all', fn ($q) => $q->whereNull('seller_reply'))
            ->with(['product', 'user', 'photos'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unanswered = ProductReview::query()->tap($own)->where('is_approved', true)->whereNull('seller_reply')->count();

        return view('seller.reviews', compact('reviews', 'unanswered'));
    }

    /**
     * Write or edit the shop's reply. The customer is emailed the first time only.
     */
    public function reply(Request $request, ProductReview $review)
    {
        $this->authorize('reply', $review);

        $data = $request->validate(['seller_reply' => 'required|string|min:2|max:1000']);

        $first = ! $review->hasReply();
        $review->forceFill([
            'seller_reply' => trim($data['seller_reply']),
            'seller_replied_at' => $first ? now() : ($review->seller_replied_at ?? now()),
            'seller_reply_user_id' => Auth::id(), // who wrote it (the owner or one of the shop's staff)
        ])->save();

        if ($first && $review->user?->email) {
            try {
                $review->user->notify(new ReviewReplied($review));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', $first ? __('Your reply is now shown under the review.') : __('Reply updated.'));
    }
}

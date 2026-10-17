{{-- Seller product form: an edit to this live product waiting for review (with Withdraw), or how the
     last one went. Shoppers see the approved version until an admin approves an edit (ProductReviewService). --}}
@if(! empty($pendingChange))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-pending-change>
        <p class="font-semibold">{{ __('Changes waiting for review since :date', ['date' => $pendingChange->created_at->format('d M Y, H:i')]) }}</p>
        <p class="mt-1">{{ __('Shoppers see the current version until an admin approves your changes. The form below shows your waiting changes, so you can add to them; saving again replaces them. Stock changes are always live at once.') }}</p>
        <form method="POST" action="{{ route('seller.products.changes.withdraw', $product) }}" class="mt-2" onsubmit="return confirm(@js(__('Withdraw your waiting changes? The product stays as shoppers see it now.')))">
            @csrf
            <button class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100">{{ __('Withdraw changes') }}</button>
        </form>
    </div>
@elseif(! empty($lastReview) && $lastReview->status === \App\Models\ProductChangeRequest::REJECTED)
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-change-rejected>
        <p class="font-semibold">{{ __('Your last changes were not approved (:date).', ['date' => $lastReview->reviewed_at?->format('d M Y')]) }}</p>
        <p class="mt-1">{{ __('Reason: :reason', ['reason' => $lastReview->rejection_reason]) }}</p>
    </div>
@elseif($product->exists && $product->isApproved() && ! app(\App\Services\ProductReviewService::class)->trusts($product))
    <p class="mb-4 rounded-lg bg-gray-50 px-4 py-2 text-xs text-gray-600">{{ __('This product is live. Changes you save here are checked by iruali before shoppers see them; stock changes are live at once.') }}</p>
@endif

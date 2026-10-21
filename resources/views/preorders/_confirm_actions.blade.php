{{-- "I can get it" or "I can't get it" for a pre-order paid once the shop confirms (App\Services\PreorderConfirmService):
     on the shop's and the admin's order pages and pre-order lists. Needs $confirmUrl and $declineUrl. --}}
<div class="flex flex-wrap gap-2" data-confirm-actions>
    <form method="POST" action="{{ $confirmUrl }}">
        @csrf
        <button class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('I can get it: ask the customer to pay') }}</button>
    </form>
    <form method="POST" action="{{ $declineUrl }}" onsubmit="return confirm(@js(__('Cancel this order because the item can\'t be had? The customer is told, and has paid nothing.')))">
        @csrf
        <button class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('I can\'t get it: cancel the order') }}</button>
    </form>
</div>

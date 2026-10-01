{{-- Disputes on one shop part: the timeline of each, and the form to open one when allowed. Needs $order, $part. --}}
@php
    $disputeService = app(\App\Services\DisputeService::class);
    $disputeTypes = $disputeService->availableTypes($part, auth()->user());
    $maxClaim = $disputeTypes ? $disputeService->maxClaim($part) : 0;
@endphp

@foreach($part->disputes->sortByDesc('id') as $dispute)
    <div class="rounded-lg border border-sun bg-sun-soft/40 p-3 text-sm space-y-2 scroll-mt-36" id="dispute-{{ $dispute->id }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-medium text-gray-900">{{ __('Dispute') }} · {{ $dispute->typeLabel() }}</p>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $dispute->status_badge }}">{{ $dispute->statusLabel() }}</span>
        </div>
        <ol class="space-y-1 text-xs text-gray-700 border-s-2 border-sun ps-3">
            <li><span class="font-medium">{{ $dispute->opened_at->translatedFormat('j M Y, H:i') }}</span> — {{ __('You opened this dispute, claiming :amount.', ['amount' => \App\Support\Money::format($dispute->amount_claimed)]) }}</li>
            @if($dispute->status === 'awaiting_customer')
                <li class="text-sun-ink font-medium">{{ __('iruali is waiting for your reply in the conversation below.') }}</li>
            @elseif($dispute->status === 'awaiting_seller')
                <li>{{ __('iruali has asked the shop for more information.') }}</li>
            @elseif($dispute->isOpen())
                <li>{{ __('iruali is reviewing it. We decide within 5 business days; the shop may reply in the conversation below.') }}</li>
            @endif
            @if($dispute->resolved_at)
                <li><span class="font-medium">{{ $dispute->resolved_at->translatedFormat('j M Y, H:i') }}</span> —
                    @if($dispute->status === 'resolved_refund'){{ __('Decided in your favour: a full refund of :amount is on its way.', ['amount' => \App\Support\Money::format($dispute->amount_resolved)]) }}
                    @elseif($dispute->status === 'resolved_partial'){{ __('Decided: a partial refund of :amount is on its way.', ['amount' => \App\Support\Money::format($dispute->amount_resolved)]) }}
                    @else{{ __('Decided: the claim was not upheld.') }}@endif
                    @if($dispute->resolution_note)<span class="block text-gray-600">{{ __('Note from iruali: :note', ['note' => $dispute->resolution_note]) }}</span>@endif
                </li>
            @endif
        </ol>
    </div>
@endforeach

@if($disputeTypes)
    <details class="rounded-lg border border-gray-200 p-3" @if($errors->hasAny(['type', 'amount_claimed', 'details']) && old('dispute_part_id') == $part->id) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-primary">{{ __('Report a problem (open a dispute)') }}</summary>
        <form method="POST" action="{{ route('orders.disputes.store', [$order, $part]) }}" class="mt-3 space-y-3">
            @csrf
            <input type="hidden" name="dispute_part_id" value="{{ $part->id }}">
            <p class="text-xs text-gray-600">{{ __('iruali looks at both sides and decides on a refund. The shop sees everything you write here.') }}</p>
            <div>
                <label for="dispute-type-{{ $part->id }}" class="block text-sm font-medium text-gray-700">{{ __('What went wrong?') }}</label>
                <select id="dispute-type-{{ $part->id }}" name="type" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    @foreach($disputeTypes as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ __(\App\Models\Dispute::TYPES[$type]) }}</option>
                    @endforeach
                </select>
                @error('type')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="dispute-amount-{{ $part->id }}" class="block text-sm font-medium text-gray-700">{{ __('Amount you are claiming (MVR)') }}</label>
                <input id="dispute-amount-{{ $part->id }}" name="amount_claimed" type="number" step="0.01" min="0.01" max="{{ $maxClaim }}" value="{{ old('amount_claimed', number_format($maxClaim, 2, '.', '')) }}" required dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                <p class="mt-1 text-xs text-gray-500">{{ __('At most :amount for these items.', ['amount' => \App\Support\Money::format($maxClaim)]) }}</p>
                @error('amount_claimed')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="dispute-details-{{ $part->id }}" class="block text-sm font-medium text-gray-700">{{ __('Tell us what happened') }}</label>
                <textarea id="dispute-details-{{ $part->id }}" name="details" rows="3" minlength="10" maxlength="2000" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old('details') }}</textarea>
                @error('details')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Open dispute') }}</button>
        </form>
    </details>
@endif

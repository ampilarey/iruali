@php
    $returnService = app(\App\Services\ReturnService::class);
    $canReturn = $returnService->canRequest($part, auth()->user());
    $returnStatus = ['requested' => __('Return requested'), 'approved' => __('Return approved'), 'refunded' => __('Refunded'), 'rejected' => __('Return not accepted')];
@endphp

@foreach($part->returnRequests->sortByDesc('id') as $return)
    <div class="rounded-lg bg-gray-50 p-3 text-sm space-y-1" id="return-{{ $return->id }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-medium text-gray-900">{{ __('Return') }} · {{ $return->created_at->translatedFormat('j M Y') }}</p>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $return->status_badge }}">{{ $returnStatus[$return->status] ?? $return->status }}</span>
        </div>
        <p class="text-gray-600">{{ $return->reasonLabel() }}:
            {{ $return->items->map(fn ($l) => ($l->orderItem?->displayName() ?? __('Product')).' × '.$l->quantity)->join(', ') }}</p>
        @if($return->refund_amount !== null && $return->status !== 'rejected')
            <p class="text-gray-700">{{ __('Refund') }}: <span class="font-semibold" dir="ltr">{{ \App\Support\Money::format($return->refund_amount) }}</span>
                @if($return->refund_reference) · {{ __('Reference') }}: <span dir="ltr">{{ $return->refund_reference }}</span>@endif</p>
        @endif
        @if($return->admin_note)
            <p class="text-gray-600">{{ __('Note from iruali: :note', ['note' => $return->admin_note]) }}</p>
        @endif
        @if($return->status === 'requested')
            <p class="text-xs text-gray-500">{{ __('We reply within 2 business days. Please keep the items and their packaging until then.') }}</p>
        @endif
    </div>
@endforeach

@if($canReturn)
    <details class="rounded-lg border border-gray-200 p-3" @if($errors->hasAny(['quantities', 'reason', 'details', 'photo']) && old('part_id') == $part->id) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-primary">{{ __('Request a return') }}</summary>
        <form method="POST" action="{{ route('orders.returns.store', [$order, $part]) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
            @csrf
            <input type="hidden" name="part_id" value="{{ $part->id }}">
            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-gray-700">{{ __('Which items?') }}</legend>
                @foreach($returnService->returnableItems($part) as $row)
                    <label class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-800">{{ $row['item']->displayName() }}</span>
                        <select name="quantities[{{ $row['item']->id }}]" class="rounded-lg border-gray-300 text-sm" aria-label="{{ __('Quantity to return') }}">
                            @for($q = 0; $q <= $row['max']; $q++)
                                <option value="{{ $q }}" @selected((int) old('quantities.'.$row['item']->id, $row['max'] === 1 ? 1 : 0) === $q)>{{ $q }}</option>
                            @endfor
                        </select>
                    </label>
                @endforeach
                @error('quantities')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="reason-{{ $part->id }}" class="block text-sm font-medium text-gray-700">{{ __('Reason') }}</label>
                <select id="reason-{{ $part->id }}" name="reason" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    @foreach(\App\Models\ReturnRequest::REASONS as $key => $label)
                        <option value="{{ $key }}" @selected(old('reason') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
                @error('reason')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="details-{{ $part->id }}" class="block text-sm font-medium text-gray-700">{{ __('Tell us what happened') }}</label>
                <textarea id="details-{{ $part->id }}" name="details" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old('details') }}</textarea>
                @error('details')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="photo-{{ $part->id }}" class="block text-sm font-medium text-gray-700">{{ __('Photo (needed for damaged or wrong items)') }}</label>
                <input id="photo-{{ $part->id }}" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-700">
                @error('photo')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-gray-500">{{ __('Returns are accepted within :days days of delivery.', ['days' => \App\Support\Company::returnWindowDays()]) }} <a href="{{ route('policies.refunds') }}" class="text-primary hover:underline">{{ __('Returns policy') }}</a></p>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Send return request') }}</button>
        </form>
    </details>
@endif

@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Bank account')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('seller.settings._nav')

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($account)
            <div class="rounded-lg bg-white p-5 shadow text-sm">
                <h2 class="font-semibold text-gray-900">{{ __('Payouts go to') }}</h2>
                <p class="mt-1 text-gray-700">{{ $account->account_name }} · {{ $account->bankName() }} · <span class="font-mono" dir="ltr">{{ $account->maskedNumber() }}</span> · {{ $account->currency }}</p>
                <p class="mt-1 text-xs {{ $account->isVerified() ? 'text-green-700' : 'text-amber-700' }}">
                    {{ $account->isVerified() ? __('Verified by iruali.') : __('Not verified yet. iruali checks new or changed accounts before the first payout to them.') }}
                </p>
            </div>
        @else
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('Add your bank account so iruali can pay you. Without it your earnings stay on hold.') }}</div>
        @endif

        <form method="POST" action="{{ route('seller.settings.bank.update') }}" class="space-y-4 rounded-lg bg-white p-6 shadow" data-bank-form>
            @csrf
            @method('PUT')
            <h2 class="text-base font-semibold text-gray-900">{{ $account ? __('Change bank account') : __('Add bank account') }}</h2>
            <p class="text-sm text-gray-500">{{ __('The account must be in the Maldives and in MVR. Use the exact name on the account.') }}</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="bank" class="block text-sm font-medium text-gray-700">{{ __('Bank') }} *</label>
                    <select id="bank" name="bank" required class="{{ $field }}" data-bank-select>
                        @foreach(\App\Models\SellerBankAccount::bankLabels() as $value => $label)
                            <option value="{{ $value }}" @selected(old('bank', $account->bank ?? 'bml') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-bank-other class="{{ old('bank', $account->bank ?? 'bml') === 'other' ? '' : 'hidden' }}">
                    <label for="bank_name_other" class="block text-sm font-medium text-gray-700">{{ __('Bank name') }} *</label>
                    <input id="bank_name_other" name="bank_name_other" maxlength="100" class="{{ $field }}" value="{{ old('bank_name_other', $account->bank_name_other ?? '') }}">
                </div>
                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700">{{ __('Account name') }} *</label>
                    <input id="account_name" name="account_name" required maxlength="150" class="{{ $field }}" value="{{ old('account_name', $account->account_name ?? ($user->business_name ?: $user->name)) }}">
                </div>
                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700">{{ __('Account number') }} *</label>
                    <input id="account_number" name="account_number" required maxlength="40" inputmode="numeric" dir="ltr" class="{{ $field }}" value="{{ old('account_number') }}" placeholder="{{ $account ? $account->maskedNumber() : '' }}" autocomplete="off">
                    <p class="mt-1 text-xs text-gray-500">{{ __('BML: 13 digits starting 7730 or 7770. MIB: 16 digits starting 90.') }}</p>
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save bank details') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var select = document.querySelector('[data-bank-select]'), other = document.querySelector('[data-bank-other]');
        if (!select || !other) return;
        select.addEventListener('change', function () { other.classList.toggle('hidden', select.value !== 'other'); });
    })();
</script>
@endpush

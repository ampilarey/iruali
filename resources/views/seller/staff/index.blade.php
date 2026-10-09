@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Staff')])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">{{ __('Let the people who help you run the shop into the Seller Centre with their own sign-in, so you never share your password. They work for your shop within their role; you can change a role or remove someone at any time, and it takes effect straight away.') }}</p>

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="overflow-hidden rounded-lg bg-white shadow" data-staff-members>
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">{{ __('Your staff') }}</h2>
            </div>
            @if($members->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-500">{{ __('Nobody else can open your Seller Centre yet. Invite someone below.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ __('Name') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Role') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Added') }}</th>
                                <th class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($members as $member)
                                <tr data-staff-member="{{ $member->id }}">
                                    <td class="px-4 py-3 align-top">
                                        <span class="font-medium text-gray-900">{{ $member->user->name ?? __('Deleted account') }}</span>
                                        <span class="block text-xs text-gray-500" dir="ltr">{{ $member->user?->email }}</span>
                                        @if($shop->staff_require_two_factor && $member->user && ! $member->user->isTwoFactorEnabled())
                                            <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Two-step sign-in not set up yet') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <form method="POST" action="{{ route('seller.staff.update', $member) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <label for="role-{{ $member->id }}" class="sr-only">{{ __('Role') }}</label>
                                            <select id="role-{{ $member->id }}" name="role" class="rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                                @foreach($roles as $role)
                                                    <option value="{{ $role }}" @selected($member->role === $role)>{{ \App\Support\ShopStaffAccess::roleLabel($role) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('Change') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 align-top text-xs text-gray-600">
                                        {{ __(':date by :name', ['date' => $member->created_at?->translatedFormat('j M Y'), 'name' => $member->inviter->name ?? __('you')]) }}
                                    </td>
                                    <td class="px-4 py-3 align-top text-end">
                                        <form method="POST" action="{{ route('seller.staff.destroy', $member) }}" onsubmit="return confirm(@js(__('Remove :name from your staff? They lose access straight away.', ['name' => $member->user->name ?? __('this person')])))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:text-red-700">{{ __('Remove') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if($invitations->isNotEmpty())
            <section class="overflow-hidden rounded-lg bg-white shadow" data-staff-invitations>
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Invitations') }}</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($invitations as $invitation)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm" data-staff-invitation="{{ $invitation->id }}">
                            <div>
                                <span class="font-medium text-gray-900" dir="ltr">{{ $invitation->email }}</span>
                                <span class="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $invitation->roleLabel() }}</span>
                                <span class="block text-xs text-gray-500">
                                    @if($invitation->isPending())
                                        {{ __('Sent :date by :name · the link works until :until', ['date' => $invitation->updated_at?->translatedFormat('j M Y'), 'name' => $invitation->inviter->name ?? __('you'), 'until' => $invitation->expires_at?->translatedFormat('j M Y, H:i')]) }}
                                    @else
                                        <span class="font-medium text-amber-700">{{ __('The link ran out on :date.', ['date' => $invitation->expires_at?->translatedFormat('j M Y')]) }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <form method="POST" action="{{ route('seller.staff.invite') }}">
                                    @csrf
                                    <input type="hidden" name="email" value="{{ $invitation->email }}">
                                    <input type="hidden" name="role" value="{{ $invitation->role }}">
                                    <button type="submit" class="font-medium text-primary-600 hover:text-primary-700">{{ __('Send again') }}</button>
                                </form>
                                @if($invitation->isPending())
                                    <form method="POST" action="{{ route('seller.staff.invitations.revoke', $invitation) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-700">{{ __('Withdraw') }}</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="rounded-lg bg-white p-6 shadow space-y-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ __('Invite someone') }}</h2>
                <p class="text-sm text-gray-500">{{ __('They get an email with a link that works for :days days. With an iruali account they sign in with it; without one they make one. An account can work for one shop only, and shop owners cannot join another shop\'s staff.', ['days' => (int) config('shop_staff.invitation_days', 7)]) }}</p>
            </div>
            <form method="POST" action="{{ route('seller.staff.invite') }}" class="grid gap-4 sm:grid-cols-3">
                @csrf
                <div class="sm:col-span-2">
                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" required maxlength="255" autocomplete="off" dir="ltr" class="{{ $field }}" value="{{ old('email') }}">
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">{{ __('Role') }}</label>
                    <select id="role" name="role" class="{{ $field }}">
                        @foreach($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', 'packer') === $role)>{{ \App\Support\ShopStaffAccess::roleLabel($role) }}</option>
                        @endforeach
                    </select>
                </div>
                <dl class="sm:col-span-3 space-y-1 text-xs text-gray-600">
                    @foreach($roles as $role)
                        <div><dt class="inline font-semibold text-gray-800">{{ \App\Support\ShopStaffAccess::roleLabel($role) }}:</dt> <dd class="inline">{{ \App\Support\ShopStaffAccess::roleDescription($role) }}</dd></div>
                    @endforeach
                    <div class="text-gray-500">{{ __('Only you see the bank details, earnings and payouts, tax registration and business documents, and only you manage staff.') }}</div>
                </dl>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Send invitation') }}</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ route('seller.staff.settings') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <h2 class="text-base font-semibold text-gray-900">{{ __('Two-step sign-in') }}</h2>
                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="hidden" name="staff_require_two_factor" value="0">
                    <input type="checkbox" name="staff_require_two_factor" value="1" @checked($shop->staff_require_two_factor) class="mt-0.5 h-5 w-5 rounded border-gray-300 text-primary-600">
                    <span><span class="font-medium text-gray-900">{{ __('Staff must use two-step sign-in') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('Before they can open the Seller Centre they set up a code from an authenticator app, which they then need each time they sign in.') }}</span></span>
                </label>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Save') }}</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection

@extends('layouts.app')

@php
    $editing = $issue->exists;
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $days = old('new_arrivals_days', $issue->newArrivalDays() ?? \App\Models\NewsletterIssue::DEFAULT_NEW_ARRIVAL_DAYS);
@endphp

@section('title', $editing ? 'Edit newsletter' : 'Write a newsletter')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.moderation._header', ['title' => $editing ? 'Edit newsletter' : 'Write a newsletter'])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $editing ? route('admin.newsletter-issues.update', $issue) : route('admin.newsletter-issues.store') }}" class="space-y-6">
            @csrf
            @if($editing) @method('PUT') @endif

            <section class="rounded-lg bg-white p-6 shadow space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Subject and opening text</h2>
                    <p class="text-sm text-gray-500">Each reader gets the newsletter in the language they chose. Both languages are needed before it can be sent; a draft can be saved with English only. Leave a blank line between paragraphs.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="subject_en" class="block text-sm font-medium text-gray-700">Subject (English) *</label>
                        <input id="subject_en" name="subject_en" required maxlength="150" class="{{ $field }}" value="{{ old('subject_en', $issue->subject_en) }}">
                    </div>
                    <div>
                        <label for="subject_dv" class="block text-sm font-medium text-gray-700">Subject (Dhivehi)</label>
                        <input id="subject_dv" name="subject_dv" maxlength="150" dir="rtl" class="{{ $field }}" value="{{ old('subject_dv', $issue->subject_dv) }}">
                    </div>
                    <div>
                        <label for="intro_en" class="block text-sm font-medium text-gray-700">Text (English) *</label>
                        <textarea id="intro_en" name="intro_en" required maxlength="5000" rows="8" class="{{ $field }}">{{ old('intro_en', $issue->intro_en) }}</textarea>
                    </div>
                    <div>
                        <label for="intro_dv" class="block text-sm font-medium text-gray-700">Text (Dhivehi)</label>
                        <textarea id="intro_dv" name="intro_dv" maxlength="5000" rows="8" dir="rtl" class="{{ $field }}">{{ old('intro_dv', $issue->intro_dv) }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Sections added after the text</h2>
                    <p class="text-sm text-gray-500">Picked by the site when you press Send: products on show and in stock, up to {{ \App\Services\NewsletterService::SECTION_PRODUCTS }} in each section, none listed twice.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="new_arrivals" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" name="new_arrivals" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" @checked(old('new_arrivals', $issue->newArrivalDays() !== null))>
                        New arrivals from the last
                    </label>
                    <label for="new_arrivals_days" class="sr-only">Number of days</label>
                    <input id="new_arrivals_days" name="new_arrivals_days" type="number" min="1" max="90" class="w-20 rounded-lg border border-gray-300 px-2 py-1 text-sm" value="{{ $days }}">
                    <span class="text-sm text-gray-800">days</span>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="deals" value="0">
                    <input type="checkbox" name="deals" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" @checked(old('deals', $issue->wantsDeals()))>
                    Current deals (the biggest markdowns)
                </label>
                <div>
                    <label for="campaign_id" class="block text-sm font-medium text-gray-700">A running campaign</label>
                    <select id="campaign_id" name="campaign_id" class="{{ $field }} sm:max-w-md">
                        <option value="">None</option>
                        @foreach($campaigns as $campaignOption)
                            <option value="{{ $campaignOption->id }}" @selected((string) old('campaign_id', $issue->campaignId()) === (string) $campaignOption->id)>{{ $campaignOption->name }} (until {{ $campaignOption->ends_at->format('j M') }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Its headline, its approved products and a link to its page. Left out if it has ended by the time you send.</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="brands" value="0">
                    <input type="checkbox" name="brands" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" @checked(old('brands', $issue->wantsBrands()))>
                    Featured brands (the brands people buy most)
                </label>
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ $editing ? route('admin.newsletter-issues.show', $issue) : route('admin.newsletter') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancel</a>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Save and preview</button>
            </div>
        </form>
    </div>
</div>
@endsection

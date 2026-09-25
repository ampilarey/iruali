<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Edit the policy pages BML reviews, like Bake & Grill's Content Hub → Legal:
 * each page shows iruali's built-in text unless the owner writes their own here.
 */
class LegalPagesController extends Controller
{
    public const PAGES = [
        'terms' => ['Terms & Conditions', 'policies.terms'],
        'refunds' => ['Returns, Refunds & Cancellations', 'policies.refunds'],
        'delivery' => ['Delivery Policy', 'policies.delivery'],
        'privacy' => ['Privacy Policy', 'policies.privacy'],
        'security' => ['Payment Security', 'policies.security'],
    ];

    public function edit()
    {
        $values = collect(self::PAGES)->keys()
            ->mapWithKeys(fn ($key) => ["legal_{$key}_body" => (string) Setting::get("legal_{$key}_body")])
            ->put('legal_last_updated_date', (string) Setting::get('legal_last_updated_date'));

        return view('admin.legal.edit', ['pages' => self::PAGES, 'values' => $values]);
    }

    public function update(Request $request)
    {
        $rules = ['legal_last_updated_date' => 'nullable|string|max:40'];
        foreach (array_keys(self::PAGES) as $key) {
            $rules["legal_{$key}_body"] = 'nullable|string|max:30000';
        }

        $data = $request->validate($rules);
        Setting::set(array_map(fn ($v) => $v === null ? '' : trim($v), $data));

        return redirect()->route('admin.legal')->with('success', 'Legal pages saved.');
    }
}

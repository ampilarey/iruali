<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SellerTerms;
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
        'seller_terms' => ['Seller Terms', 'policies.seller_terms'],
    ];

    public function edit()
    {
        $values = collect(self::PAGES)->keys()
            ->mapWithKeys(fn ($key) => ["legal_{$key}_body" => (string) Setting::get("legal_{$key}_body")])
            ->put('legal_last_updated_date', (string) Setting::get('legal_last_updated_date'));

        $shops = SellerTerms::shops()->count();

        return view('admin.legal.edit', [
            'pages' => self::PAGES,
            'values' => $values,
            'terms' => [
                'version' => SellerTerms::versionLabel(),
                'shops' => $shops,
                'agreed' => SellerTerms::shops()->where('seller_terms_version', SellerTerms::version())->count(),
                'changes' => SellerTerms::changes(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'legal_last_updated_date' => 'nullable|string|max:40',
            // A new version of the Seller Terms: every shop must agree again (SellerTerms::publish())
            'seller_terms_publish' => 'nullable|boolean',
            'seller_terms_changes' => 'nullable|required_if_accepted:seller_terms_publish|string|max:2000',
        ];
        foreach (array_keys(self::PAGES) as $key) {
            $rules["legal_{$key}_body"] = 'nullable|string|max:30000';
        }

        $data = $request->validate($rules, [
            'seller_terms_changes.required_if_accepted' => 'Say briefly what changed: shops see it when they are asked to agree again.',
        ]);
        $publish = (bool) ($data['seller_terms_publish'] ?? false);
        $changes = (string) ($data['seller_terms_changes'] ?? '');
        unset($data['seller_terms_publish'], $data['seller_terms_changes']);

        Setting::set(array_map(fn ($v) => $v === null ? '' : trim($v), $data));
        \App\Support\Audit::record('legal.saved', null, ['pages' => array_keys($data)]);

        if ($publish) {
            SellerTerms::publish($changes);

            return redirect()->route('admin.legal')->with('success', 'Legal pages saved. New Seller Terms published: every shop must agree again before using the Seller Centre, and the shops have been emailed.');
        }

        return redirect()->route('admin.legal')->with('success', 'Legal pages saved.');
    }
}

<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;

/**
 * Seller Centre → Help: short guides on running a shop, in English and Dhivehi.
 * Each guide is a Blade file per language under resources/views/seller/help/{en,dv}/.
 */
class HelpController extends Controller
{
    /**
     * @return array<string, array{title: string, summary: string}>
     */
    public static function guides(): array
    {
        return [
            'listing-products' => ['title' => __('Listing a product well'), 'summary' => __('Photos, names in both languages, pricing and stock.')],
            'packing-shipping' => ['title' => __('Packing and shipping an order'), 'summary' => __('Order statuses, deadlines and island deliveries.')],
            'handling-returns' => ['title' => __('Handling a return'), 'summary' => __('The return flow, timelines and what you are charged.')],
            'commission-payouts' => ['title' => __('Commission and payouts'), 'summary' => __('How commission is worked out, when you are paid, bank files and adjustments.')],
            'getting-approved' => ['title' => __('Getting your shop approved'), 'summary' => __('The checklist every shop completes before its products go live.')],
        ];
    }

    public function index()
    {
        return view('seller.help.index', ['guides' => self::guides()]);
    }

    public function show(string $guide)
    {
        $guides = self::guides();
        abort_unless(isset($guides[$guide]), 404);

        $locale = app()->getLocale();
        $view = "seller.help.{$locale}.{$guide}";
        if (! View::exists($view)) {
            $locale = 'en';
            $view = "seller.help.en.{$guide}";
        }

        return view('seller.help.show', ['slug' => $guide, 'guide' => $guides[$guide], 'guides' => $guides, 'content' => $view, 'contentLocale' => $locale]);
    }
}

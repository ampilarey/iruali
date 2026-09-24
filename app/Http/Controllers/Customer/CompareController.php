<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Side-by-side comparison of up to four products, kept in the session.
 */
class CompareController extends Controller
{
    public const MAX = 4;

    public function index(Request $request)
    {
        $ids = $request->session()->get('compare', []);

        $products = Product::query()->active()->whereIn('id', $ids)
            ->with(['mainImage', 'seller', 'category'])
            ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
            ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating')
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids))
            ->values();

        return view('compare.index', compact('products'));
    }

    public function toggle(Request $request, Product $product)
    {
        $ids = collect($request->session()->get('compare', []));

        if ($ids->contains($product->id)) {
            $ids = $ids->reject(fn ($id) => $id === $product->id);
        } elseif ($ids->count() >= self::MAX) {
            NotificationService::info(__('You can compare up to :max products. Remove one first.', ['max' => self::MAX]));

            return back();
        } else {
            $ids->push($product->id);
        }

        $request->session()->put('compare', $ids->values()->all());

        return back();
    }

    public function clear(Request $request)
    {
        $request->session()->forget('compare');

        return redirect()->route('compare');
    }
}

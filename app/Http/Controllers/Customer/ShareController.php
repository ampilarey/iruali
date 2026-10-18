<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Product share buttons go through here so iruali knows what customers share (Admin → Social posts).
 */
class ShareController extends Controller
{
    public function go(Product $product, string $network)
    {
        $this->record($product, $network);
        $url = route('products.show', $product);
        $text = $product->name.' – '.\App\Support\Money::format((float) $product->final_price);

        return redirect()->away(match ($network) {
            'viber' => 'viber://forward?text='.rawurlencode($text.' '.$url),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.urlencode($url),
            'telegram' => 'https://t.me/share/url?url='.urlencode($url).'&text='.urlencode($text),
            default => 'https://wa.me/?text='.urlencode($text.' '.$url),
        });
    }

    public function copied(Product $product)
    {
        $this->record($product, 'copy');

        return response()->noContent();
    }

    protected function record(Product $product, string $network): void
    {
        DB::table('share_events')->insert(['product_id' => $product->id, 'network' => $network, 'created_at' => now()]);
    }
}

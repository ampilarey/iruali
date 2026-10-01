<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAlert;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate(['email' => 'required|email:rfc|max:255', 'product_variant_id' => 'nullable|integer']);

        // With variants the alert can be for one option (size, colour...) or for any of them
        $variantId = null;
        if ($product->has_variants && ! empty($data['product_variant_id'])) {
            $variantId = $product->variants()->where('is_active', true)->whereKey($data['product_variant_id'])->value('id');
            abort_unless($variantId, 404);
        }

        StockAlert::updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => $variantId, 'email' => mb_strtolower($data['email'])],
            ['user_id' => $request->user()?->id, 'locale' => app()->getLocale(), 'notified_at' => null]
        );

        NotificationService::success(__('We will email you when it is back in stock.'));

        return redirect(route('products.show', $product));
    }
}

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

        $data = $request->validate(['email' => 'required|email:rfc|max:255']);

        StockAlert::updateOrCreate(
            ['product_id' => $product->id, 'email' => mb_strtolower($data['email'])],
            ['user_id' => $request->user()?->id, 'locale' => app()->getLocale(), 'notified_at' => null]
        );

        NotificationService::success(__('We will email you when it is back in stock.'));

        return redirect(route('products.show', $product));
    }
}

<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CartService::addToCart() refuses a product that is "coming soon" (ComingSoonService). The web
 * gets the page back with the reason; the API gets a JSON error in its usual shape. Not a fault, so
 * never reported.
 */
class ProductComingSoonException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct(self::messageFor($product));
    }

    public static function messageFor(Product $product): string
    {
        return $product->available_on
            ? __('":name" is coming soon: it goes on sale on :date.', ['name' => $product->name, 'date' => $product->available_on->translatedFormat('j M Y')])
            : __('":name" is coming soon and can\'t be ordered yet.', ['name' => $product->name]);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $this->getMessage()], 422);
        }

        NotificationService::error($this->getMessage());

        return redirect()->back();
    }
}

<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Services\NotificationService;
use App\Support\ShopHoliday;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CartService::addToCart() refuses a product whose shop is on holiday. The web gets the page back
 * with the reason; the API gets a JSON error in its usual shape. It is the shop's choice, not a
 * fault, so it is never reported or counted in Admin → Errors.
 */
class ShopOnHolidayException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct(ShopHoliday::addToCartMessage($product));
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

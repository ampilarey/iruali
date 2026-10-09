<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The island a product page quotes delivery for ("Delivery to <island>"). Kept in the session,
 * so every product page then shows the fee and arrival dates for that island.
 */
class DeliveryIslandController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'island_id' => ['required', 'integer', Rule::exists(Island::class, 'id')->where('is_active', true)],
        ]);

        $request->session()->put(DeliveryService::SESSION_ISLAND, (int) $data['island_id']);

        // Back to the product page (only ever a page of this site), at the delivery box
        $root = url('/');
        $previous = url()->previous();
        $back = $previous === $root || str_starts_with($previous, $root.'/') ? $previous : $root;

        return redirect()->to($back.'#delivery-box');
    }
}

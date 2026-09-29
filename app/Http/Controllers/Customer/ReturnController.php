<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Services\NotificationService;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Customers asking to return items from a delivered shop part of their order.
 */
class ReturnController extends Controller
{
    public function store(Request $request, Order $order, SellerOrder $part, ReturnService $returns)
    {
        abort_unless($order->user_id === $request->user()->id && $part->order_id === $order->id, 403);

        if (! $returns->canRequest($part, $request->user())) {
            NotificationService::error(__('A return can\'t be requested for these items.'));

            return redirect()->route('orders.show', $order);
        }

        $data = $request->validate([
            'quantities' => 'required|array',
            'quantities.*' => 'integer|min:0|max:1000',
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'details' => 'nullable|string|max:2000',
            'photo' => [Rule::requiredIf(in_array($request->input('reason'), ['damaged', 'wrong_item'], true)), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => __('Please add a photo of the item.'),
            'photo.max' => __('The photo must be 5 MB or smaller.'),
        ]);

        $return = $returns->request($part, $request->user(), $data['quantities'], $data['reason'], $data['details'] ?? null, $request->file('photo'));

        if (! $return) {
            return back()->withInput()->withErrors(['quantities' => __('Choose at least one item to return.')]);
        }

        NotificationService::success(__('Return requested. We\'ll reply within 2 business days.'));

        return redirect()->route('orders.show', $order);
    }

    /**
     * The customer's photo: shown to the customer, the shop and admins only.
     */
    public function photo(Request $request, ReturnRequest $return)
    {
        $user = $request->user();
        abort_unless(
            $return->user_id === $user->id || $user->isAdmin() || $return->sellerOrder?->seller_id === $user->id,
            403
        );
        abort_unless($return->photo_path && Storage::disk(ReturnService::DISK)->exists($return->photo_path), 404);

        return Storage::disk(ReturnService::DISK)->response($return->photo_path);
    }
}

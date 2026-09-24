<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'total_items' => $this->whenLoaded('items', function () {
                return $this->items->sum('quantity');
            }),
            'subtotal' => $this->whenLoaded('items', fn () => (float) $this->total),
            'voucher_code' => $this->voucher_code,
            'voucher_discount' => $this->whenLoaded('items', fn () => (float) $this->voucherDiscount()),
            'total' => $this->whenLoaded('items', fn () => (float) max(0, $this->total - $this->voucherDiscount())),
            'items' => $this->whenLoaded('items', function () {
                return collect($this->items)->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'product_variant_id' => $item->product_variant_id,
                        'subtotal' => $item->quantity * $item->price,
                        'product' => new ProductResource($item->product),
                    ];
                });
            }),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Voucher discount for this cart, using the voucher stored on the cart.
     */
    protected function voucherDiscount(): float
    {
        if (! $this->voucher_code) {
            return 0;
        }

        $voucher = \App\Models\Voucher::where('code', $this->voucher_code)->where('is_active', true)->first();

        return $voucher ? app(\App\Services\DiscountService::class)->calculateVoucherAmount($this->resource, $voucher) : 0;
    }
}

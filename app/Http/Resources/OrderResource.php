<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_badge' => $this->status_badge,
            'total_amount' => $this->total_amount,
            'voucher_code' => $this->voucher_code,
            'voucher_discount' => $this->voucher_discount,
            'loyalty_points_earned' => $this->loyalty_points_earned,
            'points_redeemed' => $this->points_redeemed,
            'points_redeemed_discount' => $this->points_redeemed_discount,
            'shipping_address' => [
                'address' => $this->shipping_address,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'zip' => $this->shipping_zip,
                'country' => $this->shipping_country,
            ],
            'billing_address' => $this->billing_address,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'tracking_number' => $this->tracking_number,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return collect($this->items)->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->quantity * $item->price,
                        'product' => $item->whenLoaded('product', function () use ($item) {
                            return [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'description' => $item->product->description,
                                'sku' => $item->product->sku,
                                'slug' => $item->product->slug,
                                'main_image' => $item->product->main_image,
                                'category' => $item->product->whenLoaded('category', function () use ($item) {
                                    return [
                                        'id' => $item->product->category->id,
                                        'name' => $item->product->category->name,
                                    ];
                                }),
                            ];
                        }),
                    ];
                });
            }),
            'item_count' => $this->whenLoaded('items', function () {
                return $this->items->count();
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
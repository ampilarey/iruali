<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'profile_picture' => $this->profile_picture,
            'loyalty_points' => $this->loyalty_points,
            'is_seller' => $this->is_seller,
            'seller_approved' => $this->seller_approved,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at,
            'referral_code' => $this->referral_code,
            'referred_by' => $this->referred_by,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ];
                });
            }),
            'orders' => $this->whenLoaded('orders', function () {
                return OrderResource::collection($this->orders);
            }),
            'wishlist' => $this->whenLoaded('wishlist', function () {
                return $this->wishlist->map(function ($wishlistItem) {
                    return [
                        'id' => $wishlistItem->id,
                        'product' => new ProductResource($wishlistItem->product),
                        'created_at' => $wishlistItem->created_at,
                    ];
                });
            }),
            'cart' => $this->whenLoaded('cart', function () {
                return [
                    'id' => $this->cart->id,
                    'items' => $this->cart->whenLoaded('items', function () {
                        return $this->cart->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'quantity' => $item->quantity,
                                'product' => new ProductResource($item->product),
                            ];
                        });
                    }),
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
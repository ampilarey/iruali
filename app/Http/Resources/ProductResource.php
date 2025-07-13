<?php

namespace App\Http\Resources;

use App\Services\LocalizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => LocalizationService::getLocalizedValue($this->resource, 'name'),
            'description' => LocalizationService::getLocalizedValue($this->resource, 'description'),
            'price' => $this->price,
            'final_price' => $this->final_price,
            'compare_price' => $this->compare_price,
            'sale_price' => $this->sale_price,
            'discount_percentage' => $this->discount_percentage,
            'is_on_sale' => $this->is_on_sale,
            'stock_quantity' => $this->stock_quantity,
            'is_in_stock' => $this->is_in_stock,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'main_image' => $this->main_image,
            'images' => $this->whenLoaded('images', function () {
                return collect($this->images)->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                        'alt' => $image->alt,
                        'is_main' => $image->is_main,
                    ];
                });
            }),
            'tags' => $this->tags,
            'brand' => $this->brand,
            'model' => $this->model,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'requires_shipping' => $this->requires_shipping,
            'is_digital' => $this->is_digital,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            'seller' => $this->whenLoaded('seller', function () {
                return [
                    'id' => $this->seller->id,
                    'name' => $this->seller->name,
                ];
            }),
            'reviews' => $this->whenLoaded('reviews', function () {
                return collect($this->reviews)->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'user' => $review->whenLoaded('user', function () use ($review) {
                            return [
                                'id' => $review->user->id,
                                'name' => $review->user->name,
                            ];
                        }),
                        'created_at' => $review->created_at,
                    ];
                });
            }),
            'variants' => $this->whenLoaded('variants', function () {
                return collect($this->variants)->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => $variant->price,
                        'stock_quantity' => $variant->stock_quantity,
                    ];
                });
            }),
            'is_featured' => $this->is_featured,
            'is_sponsored' => $this->is_sponsored,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
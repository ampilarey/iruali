<?php

namespace App\Http\Resources;

use App\Services\LocalizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'slug' => $this->slug,
            'image' => $this->image,
            'is_active' => $this->is_active,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', function () {
                return new CategoryResource($this->parent);
            }),
            'children' => $this->whenLoaded('children', function () {
                return CategoryResource::collection($this->children);
            }),
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
            'products' => $this->whenLoaded('products', function () {
                return ProductResource::collection($this->products);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
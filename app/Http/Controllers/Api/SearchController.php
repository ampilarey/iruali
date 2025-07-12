<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends BaseController
{
    /**
     * Search products
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'sort_by' => 'nullable|in:price_asc,price_desc,name_asc,name_desc,relevance',
            'in_stock' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $query = $request->q;
        
        $productQuery = Product::with(['category', 'mainImage', 'seller'])
            ->active()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->whereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ['%' . $query . '%'])
                  ->orWhereRaw("JSON_EXTRACT(description, '$.en') LIKE ?", ['%' . $query . '%'])
                  ->orWhere('sku', 'LIKE', '%' . $query . '%')
                  ->orWhere('brand', 'LIKE', '%' . $query . '%')
                  ->orWhere('model', 'LIKE', '%' . $query . '%');
            });

        // Apply filters
        if ($request->category_id) {
            $productQuery->where('category_id', $request->category_id);
        }

        if ($request->min_price) {
            $productQuery->where('price', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $productQuery->where('price', '<=', $request->max_price);
        }

        if ($request->in_stock) {
            $productQuery->inStock();
        }

        // Apply sorting
        switch ($request->sort_by) {
            case 'price_asc':
                $productQuery->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $productQuery->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $productQuery->orderByRaw("JSON_EXTRACT(name, '$.en') ASC");
                break;
            case 'name_desc':
                $productQuery->orderByRaw("JSON_EXTRACT(name, '$.en') DESC");
                break;
            case 'relevance':
                // Order by relevance (exact matches first, then partial matches)
                $productQuery->orderByRaw("
                    CASE 
                        WHEN JSON_EXTRACT(name, '$.en') LIKE ? THEN 1
                        WHEN JSON_EXTRACT(description, '$.en') LIKE ? THEN 2
                        WHEN sku LIKE ? THEN 3
                        ELSE 4
                    END
                ", [$query, $query, $query]);
                break;
            default:
                $productQuery->orderBy('created_at', 'desc');
        }

        $perPage = $request->per_page ?? 15;
        $products = $productQuery->paginate($perPage);

        // Transform the products
        $products->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'final_price' => $product->final_price,
                'compare_price' => $product->compare_price,
                'sale_price' => $product->sale_price,
                'discount_percentage' => $product->discount_percentage,
                'is_on_sale' => $product->is_on_sale,
                'stock_quantity' => $product->stock_quantity,
                'is_in_stock' => $product->is_in_stock,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'main_image' => $product->main_image,
                'images' => $product->images,
                'brand' => $product->brand,
                'model' => $product->model,
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ],
                'seller' => [
                    'id' => $product->seller->id,
                    'name' => $product->seller->name,
                ],
                'is_featured' => $product->is_featured,
                'is_sponsored' => $product->is_sponsored,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        });

        return $this->sendResponse([
            'query' => $query,
            'products' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ], 'Search results retrieved successfully');
    }
}

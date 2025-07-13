<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ProductResource;

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

        return $this->sendResponse([
            'query' => $query,
            'products' => ProductResource::collection($products),
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

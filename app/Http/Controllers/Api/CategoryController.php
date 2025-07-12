<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends BaseController
{
    /**
     * Get all categories
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:categories,id',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $query = Category::with(['children', 'parent']);

        if ($request->parent_id) {
            $query->where('parent_id', $request->parent_id);
        } elseif ($request->has('parent_id') && $request->parent_id === null) {
            $query->whereNull('parent_id');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->active();
        }

        $categories = $query->orderBy('name')->get();

        $categories->transform(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'slug' => $category->slug,
                'image' => $category->image,
                'status' => $category->status,
                'parent_id' => $category->parent_id,
                'parent' => $category->parent ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->name,
                    'slug' => $category->parent->slug,
                ] : null,
                'children' => $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'status' => $child->status,
                    ];
                }),
                'full_path' => $category->full_path,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        });

        return $this->sendResponse($categories, 'Categories retrieved successfully');
    }

    /**
     * Get a specific category with its products
     */
    public function show(Request $request, Category $category)
    {
        if ($category->status !== 'active') {
            return $this->sendNotFound('Category not found');
        }

        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:price_asc,price_desc,name_asc,name_desc,created_at_desc',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $query = $category->products()
            ->with(['mainImage', 'seller'])
            ->active()
            ->where('is_active', true);

        // Apply filters
        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->in_stock) {
            $query->inStock();
        }

        // Apply sorting
        switch ($request->sort_by) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderByRaw("JSON_EXTRACT(name, '$.en') ASC");
                break;
            case 'name_desc':
                $query->orderByRaw("JSON_EXTRACT(name, '$.en') DESC");
                break;
            case 'created_at_desc':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->per_page ?? 15;
        $products = $query->paginate($perPage);

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

        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'slug' => $category->slug,
            'image' => $category->image,
            'status' => $category->status,
            'parent_id' => $category->parent_id,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
            ] : null,
            'children' => $category->children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'status' => $child->status,
                ];
            }),
            'full_path' => $category->full_path,
            'products' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];

        return $this->sendResponse($categoryData, 'Category retrieved successfully');
    }
}

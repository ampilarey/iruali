<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use App\Http\Resources\ProductResource;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\StoreProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends BaseController
{
    /**
     * Get all products with pagination and filters
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'search' => 'nullable|string|max:255',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'sort_by' => 'nullable|in:price_asc,price_desc,name_asc,name_desc,created_at_desc',
            'in_stock' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $query = Product::with(['category', 'mainImage', 'seller'])
            ->active()
            ->where('is_active', true);

        // Apply filters
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ['%' . $request->search . '%'])
                  ->orWhereRaw("JSON_EXTRACT(description, '$.en') LIKE ?", ['%' . $request->search . '%'])
                  ->orWhere('sku', 'LIKE', '%' . $request->search . '%');
            });
        }

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

        return $this->sendResponse([
            'products' => ProductResource::collection($products),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ], 'Products retrieved successfully');
    }

    /**
     * Get a specific product
     */
    public function show(Product $product)
    {
        if (!$product->is_active) {
            return $this->sendNotFound('Product not found');
        }

        $product->load(['category', 'images', 'reviews.user', 'seller', 'variants']);

        return $this->sendResponse(new ProductResource($product), 'Product retrieved successfully');
    }

    /**
     * Get featured products
     */
    public function featured(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $limit = $request->limit ?? 10;
        
        $products = Product::with(['category', 'mainImage', 'seller'])
            ->active()
            ->featured()
            ->inStock()
            ->limit($limit)
            ->get();

        $products->transform(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'final_price' => $product->final_price,
                'discount_percentage' => $product->discount_percentage,
                'is_on_sale' => $product->is_on_sale,
                'stock_quantity' => $product->stock_quantity,
                'is_in_stock' => $product->is_in_stock,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'main_image' => $product->main_image,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->name,
                ] : null,
            ];
        });

        return $this->sendResponse($products, 'Featured products retrieved successfully');
    }

    /**
     * Get products on sale
     */
    public function onSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $limit = $request->limit ?? 10;
        
        $products = Product::with(['category', 'mainImage', 'seller'])
            ->active()
            ->whereNotNull('sale_price')
            ->where('sale_price', '<', \DB::raw('price'))
            ->inStock()
            ->orderBy('discount_percentage', 'desc')
            ->limit($limit)
            ->get();

        $products->transform(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'final_price' => $product->final_price,
                'sale_price' => $product->sale_price,
                'discount_percentage' => $product->discount_percentage,
                'stock_quantity' => $product->stock_quantity,
                'is_in_stock' => $product->is_in_stock,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'main_image' => $product->main_image,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->name,
                ] : null,
            ];
        });

        return $this->sendResponse($products, 'Products on sale retrieved successfully');
    }

    /**
     * Create a new product
     */
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        
        // Set the seller_id to the authenticated user
        $data['seller_id'] = auth()->id();
        
        $product = Product::create($data);

        return $this->sendResponse(new ProductResource($product), 'Product created successfully', 201);
    }
}

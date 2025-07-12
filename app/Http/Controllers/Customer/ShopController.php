<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock();

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filter by sale items
        if ($request->has('sale')) {
            $query->whereNotNull('sale_price');
        }

        // Sort products
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'popular':
                $query->withCount('reviews')->orderBy('reviews_count', 'desc');
                break;
            default:
                $query->latest();
        }

        $products = $query->paginate(12);
        $categories = Category::active()->root()->get();

        return view('shop.index', compact('products', 'categories'));
    }

    public function category(Category $category)
    {
        $products = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock()
            ->where('category_id', $category->id)
            ->latest()
            ->paginate(12);

        $subcategories = $category->children()->active()->get();

        return view('shop.category', compact('category', 'products', 'subcategories'));
    }
}

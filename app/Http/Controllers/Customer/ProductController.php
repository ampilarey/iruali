<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock()
            ->latest()
            ->paginate(12);

        $categories = Category::active()->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        // Check if product is active
        if (!$product->is_active) {
            abort(404);
        }

        // Load related data
        $product->load(['category', 'images', 'reviews.user', 'variants']);

        // Get related products
        $relatedProducts = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'relatedProducts'));
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

        return view('products.category', compact('category', 'products', 'subcategories'));
    }
}

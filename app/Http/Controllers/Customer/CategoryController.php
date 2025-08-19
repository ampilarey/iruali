<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with(['products' => function($query) {
            $query->active()->inStock();
        }])
        ->active()
        ->root()
        ->get();

        return view('categories.index', compact('categories'));
    }

    public function show(Category $category)
    {
        // Check if category is active
        if (!$category->is_active) {
            abort(404);
        }

        $products = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock()
            ->where('category_id', $category->id)
            ->latest()
            ->paginate(12);

        $subcategories = $category->children()->active()->get();

        return view('categories.show', compact('category', 'products', 'subcategories'));
    }
}

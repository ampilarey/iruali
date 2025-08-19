<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with(['products' => function ($query) {
            $query->active()->inStock();
        }])
        ->active()
        ->root()
        ->get();

        return view('categories.index', compact('categories'));
    }

    public function show(Category $category)
    {
        $products = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock()
            ->where('category_id', $category->id)
            ->latest()
            ->paginate(12);

        $subcategories = $category->children()->active()->get();
        $parentCategories = $this->getParentCategories($category);

        return view('categories.show', compact('category', 'products', 'subcategories', 'parentCategories'));
    }

    private function getParentCategories($category)
    {
        $parents = [];
        $current = $category;

        while ($current->parent) {
            array_unshift($parents, $current->parent);
            $current = $current->parent;
        }

        return $parents;
    }
}

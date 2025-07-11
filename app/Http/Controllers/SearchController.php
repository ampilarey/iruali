<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->get('q');
        
        if (!$query) {
            return redirect()->route('home');
        }

        $products = Product::with(['category', 'mainImage'])
            ->active()
            ->inStock()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhereHas('category', function ($categoryQuery) use ($query) {
                      $categoryQuery->where('name', 'like', "%{$query}%");
                  });
            })
            ->latest()
            ->paginate(12);

        $categories = Category::active()->root()->get();

        return view('search.index', compact('products', 'categories', 'query'));
    }
}

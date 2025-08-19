<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        if (!$query) {
            return redirect()->route('home');
        }

        $products = Product::with(['category', 'mainImage'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhereHas('category', function ($categoryQuery) use ($query) {
                      $categoryQuery->where('name', 'like', "%{$query}%");
                  });
            });

        // Apply category filter
        if ($request->has('category') && $request->category) {
            $products->where('category_id', $request->category);
        }

        // Apply price filters
        if ($request->has('min_price') && $request->min_price) {
            $products->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price') && $request->max_price) {
            $products->where('price', '<=', $request->max_price);
        }

        $products = $products->latest()->paginate(12);
        $categories = Category::active()->root()->get();

        $totalResults = $products->total();
        
        return view('search.results', compact('products', 'categories', 'query', 'totalResults'));
    }

    public function index(Request $request)
    {
        // If no search query, show the search form
        if (!$request->get('q')) {
            return view('search.index');
        }
        
        // Otherwise, perform the search
        return $this->search($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Banner;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::with(['category', 'mainImage'])
            ->active()
            ->featured()
            ->inStock()
            ->latest()
            ->take(8)
            ->get();

        $categories = Category::with(['products' => function ($query) {
            $query->active()->inStock();
        }])
        ->active()
        ->root()
        ->take(6)
        ->get();

        $banners = Banner::active()->latest()->take(3)->get();

        return view('home', compact('featuredProducts', 'categories', 'banners'));
    }
}

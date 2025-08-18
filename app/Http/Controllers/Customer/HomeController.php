<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Banner;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        // Get categories with product count
        $categories = Category::withCount('products')->get();
        
        // Get featured products (if none exist, get latest products)
        $featuredProducts = Product::with(['images', 'category'])
            ->where('is_featured', true)
            ->where('is_active', true)
            ->take(8)
            ->get();
            
        // If no featured products, get latest products
        if ($featuredProducts->isEmpty()) {
            $featuredProducts = Product::with(['images', 'category'])
                ->where('is_active', true)
                ->latest()
                ->take(8)
                ->get();
        }
        
        // Get active banners for homepage
        $banners = Banner::where('status', 'active')
            ->where('position', 'homepage')
            ->get();
            
        // Flash Sale Products
        $flashSaleProducts = Product::with(['images', 'category'])
            ->whereNotNull('flash_sale_ends_at')
            ->where('flash_sale_ends_at', '>', Carbon::now())
            ->where('is_active', true)
            ->orderBy('flash_sale_ends_at')
            ->take(8)
            ->get();
            
        return view('home', compact('categories', 'featuredProducts', 'banners', 'flashSaleProducts'));
    }
}

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
        $categories = Category::with('products')->get();
        $featuredProducts = Product::where('is_featured', true)->where('is_active', true)->take(8)->get();
        $banners = Banner::where('status', 'active')->where('position', 'homepage')->get();
        // Flash Sale Products
        $flashSaleProducts = Product::whereNotNull('flash_sale_ends_at')
            ->where('flash_sale_ends_at', '>', Carbon::now())
            ->where('is_active', true)
            ->orderBy('flash_sale_ends_at')
            ->take(8)
            ->get();
        return view('home', compact('categories', 'featuredProducts', 'banners', 'flashSaleProducts'));
    }
}

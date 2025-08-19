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
        
        // Use fallback products with proper images for now
        $featuredProducts = collect([
            [
                'id' => 1,
                'name' => 'Wireless Bluetooth Headphones',
                'brand' => 'TechAudio',
                'price' => 89.99,
                'compare_price' => 129.99,
                'stock_quantity' => 25,
                'is_featured' => true,
                'is_active' => true,
                'category' => (object)['name' => 'Electronics'],
                'image_path' => '/images/products/headphones.svg'
            ],
            [
                'id' => 2,
                'name' => 'Premium Cotton T-Shirt',
                'brand' => 'FashionCo',
                'price' => 24.99,
                'compare_price' => null,
                'stock_quantity' => 50,
                'is_featured' => true,
                'is_active' => true,
                'category' => (object)['name' => 'Fashion'],
                'image_path' => '/images/products/denim-jacket.svg'
            ],
            [
                'id' => 3,
                'name' => 'Smart Fitness Watch',
                'brand' => 'FitTech',
                'price' => 199.99,
                'compare_price' => 299.99,
                'stock_quantity' => 15,
                'is_featured' => true,
                'is_active' => true,
                'category' => (object)['name' => 'Electronics'],
                'image_path' => '/images/products/smartphone.svg'
            ],
            [
                'id' => 4,
                'name' => 'Organic Coffee Beans',
                'brand' => 'BrewMaster',
                'price' => 19.99,
                'compare_price' => null,
                'stock_quantity' => 100,
                'is_featured' => true,
                'is_active' => true,
                'category' => (object)['name' => 'Food & Beverage'],
                'image_path' => '/images/products/coffee-table.svg'
            ],
            [
                'id' => 5,
                'name' => 'Professional Camera Lens',
                'brand' => 'PhotoPro',
                'price' => 599.99,
                'compare_price' => 799.99,
                'stock_quantity' => 8,
                'is_featured' => true,
                'is_active' => true,
                'category' => (object)['name' => 'Electronics'],
                'image_path' => '/images/products/laptop.svg'
            ]
        ]);
        
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

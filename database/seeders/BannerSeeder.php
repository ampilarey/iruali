<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Summer Sale',
                'description' => 'Up to 50% off on selected items',
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
                'button_text' => 'Shop Now',
                'button_url' => '/shop',
                'status' => 'active',
                'position' => 'homepage'
            ],
            [
                'title' => 'New Arrivals',
                'description' => 'Discover the latest products in our collection',
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
                'button_text' => 'Explore',
                'button_url' => '/products',
                'status' => 'active',
                'position' => 'homepage'
            ],
            [
                'title' => 'Free Shipping',
                'description' => 'Free shipping on orders over $50',
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
                'button_text' => 'Learn More',
                'button_url' => '/shipping',
                'status' => 'active',
                'position' => 'homepage'
            ]
        ];

        foreach ($banners as $bannerData) {
            Banner::create($bannerData);
        }
    }
} 
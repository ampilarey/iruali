#!/bin/bash

# iruali Add Test Products Script
# This script adds test products for search functionality

echo "📦 Adding test products for search functionality..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script in your Laravel project root."
    exit 1
fi

# Check if products already exist
PRODUCT_COUNT=$(php artisan tinker --execute="echo App\Models\Product::count();" 2>/dev/null | tail -1)

if [ "$PRODUCT_COUNT" -gt 0 ]; then
    echo "✅ Products already exist ($PRODUCT_COUNT products found)."
    echo "Search functionality should work with existing products."
    exit 0
fi

echo "📊 No products found. Adding test products..."

# Create a simple PHP script to add products
cat > add_products.php << 'EOF'
<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

// Get admin user or create one
$admin = User::where('email', 'admin@example.com')->first();
if (!$admin) {
    echo "❌ Admin user not found. Please run the full seeding first.\n";
    exit(1);
}

// Get or create categories
$electronics = Category::where('slug', 'electronics')->first();
$clothing = Category::where('slug', 'clothing')->first();

if (!$electronics) {
    $electronics = Category::create([
        'name' => 'Electronics',
        'description' => 'Latest electronic devices and gadgets',
        'slug' => 'electronics',
        'status' => 'active'
    ]);
}

if (!$clothing) {
    $clothing = Category::create([
        'name' => 'Clothing',
        'description' => 'Fashion and apparel',
        'slug' => 'clothing',
        'status' => 'active'
    ]);
}

// Sample products
$products = [
    [
        'name' => 'iPhone 15 Pro',
        'description' => 'Latest iPhone with advanced features',
        'price' => 999.99,
        'sku' => 'IPH15PRO001',
        'category_id' => $electronics->id,
        'seller_id' => $admin->id,
        'stock_quantity' => 50,
        'is_active' => true,
        'slug' => 'iphone-15-pro'
    ],
    [
        'name' => 'MacBook Air M2',
        'description' => 'Powerful laptop with M2 chip',
        'price' => 1199.99,
        'sku' => 'MBAIRM2001',
        'category_id' => $electronics->id,
        'seller_id' => $admin->id,
        'stock_quantity' => 30,
        'is_active' => true,
        'slug' => 'macbook-air-m2'
    ],
    [
        'name' => 'Nike Air Max 270',
        'description' => 'Comfortable running shoes',
        'price' => 129.99,
        'sku' => 'NIKE270001',
        'category_id' => $clothing->id,
        'seller_id' => $admin->id,
        'stock_quantity' => 100,
        'is_active' => true,
        'slug' => 'nike-air-max-270'
    ],
    [
        'name' => 'Samsung Galaxy S24',
        'description' => 'Android flagship smartphone',
        'price' => 899.99,
        'sku' => 'SAMS24PRO001',
        'category_id' => $electronics->id,
        'seller_id' => $admin->id,
        'stock_quantity' => 40,
        'is_active' => true,
        'slug' => 'samsung-galaxy-s24'
    ],
    [
        'name' => 'Adidas Ultraboost',
        'description' => 'Premium running shoes',
        'price' => 179.99,
        'sku' => 'ADIDASUB001',
        'category_id' => $clothing->id,
        'seller_id' => $admin->id,
        'stock_quantity' => 75,
        'is_active' => true,
        'slug' => 'adidas-ultraboost'
    ]
];

foreach ($products as $productData) {
    Product::create($productData);
    echo "✅ Added: " . $productData['name'] . "\n";
}

echo "\n🎉 Successfully added " . count($products) . " test products!\n";
echo "Search functionality should now work.\n";
EOF

# Run the PHP script
php add_products.php

# Clean up
rm add_products.php

echo "✅ Test products added successfully!" 
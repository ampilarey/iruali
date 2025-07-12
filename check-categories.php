<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

echo "🔍 Category Diagnostic Script\n";
echo "============================\n\n";

// Check database connection
try {
    \DB::connection()->getPdo();
    echo "✅ Database connection: OK\n";
} catch (\Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if categories table exists
try {
    $tableExists = \Schema::hasTable('categories');
    echo "✅ Categories table exists: " . ($tableExists ? 'YES' : 'NO') . "\n";
} catch (\Exception $e) {
    echo "❌ Error checking categories table: " . $e->getMessage() . "\n";
}

// Check total categories count
try {
    $totalCategories = Category::count();
    echo "📊 Total categories: {$totalCategories}\n";
} catch (\Exception $e) {
    echo "❌ Error counting categories: " . $e->getMessage() . "\n";
}

// Check active categories count
try {
    $activeCategories = Category::where('status', 'active')->count();
    echo "📊 Active categories: {$activeCategories}\n";
} catch (\Exception $e) {
    echo "❌ Error counting active categories: " . $e->getMessage() . "\n";
}

// Check root categories count
try {
    $rootCategories = Category::whereNull('parent_id')->count();
    echo "📊 Root categories: {$rootCategories}\n";
} catch (\Exception $e) {
    echo "❌ Error counting root categories: " . $e->getMessage() . "\n";
}

// List all categories
try {
    echo "\n📋 All Categories:\n";
    echo "==================\n";
    $categories = Category::all();
    if ($categories->count() > 0) {
        foreach ($categories as $category) {
            $status = $category->status === 'active' ? '✅' : '❌';
            $parent = $category->parent_id ? " (Parent ID: {$category->parent_id})" : " (Root)";
            echo "{$status} {$category->name} - {$category->slug}{$parent}\n";
        }
    } else {
        echo "No categories found in database.\n";
    }
} catch (\Exception $e) {
    echo "❌ Error listing categories: " . $e->getMessage() . "\n";
}

// Check if admin user exists
try {
    $adminUser = User::where('email', 'admin@example.com')->first();
    echo "\n👤 Admin user: " . ($adminUser ? 'EXISTS' : 'NOT FOUND') . "\n";
} catch (\Exception $e) {
    echo "❌ Error checking admin user: " . $e->getMessage() . "\n";
}

// Check products count
try {
    $totalProducts = Product::count();
    echo "📦 Total products: {$totalProducts}\n";
} catch (\Exception $e) {
    echo "❌ Error counting products: " . $e->getMessage() . "\n";
}

echo "\n🎯 Recommendations:\n";
echo "==================\n";

if ($totalCategories == 0) {
    echo "1. Run the seeding script: ./seed-production.sh\n";
    echo "2. Or run categories seeder manually: php artisan db:seed --class=CategorySeeder --force\n";
} elseif ($activeCategories == 0) {
    echo "1. Check category status values in database\n";
    echo "2. Update categories to 'active' status\n";
} else {
    echo "1. Categories seem to be working fine\n";
    echo "2. Check if the issue is with the frontend display\n";
}

echo "\n🔧 Quick Fix Commands:\n";
echo "=====================\n";
echo "php artisan db:seed --class=CategorySeeder --force\n";
echo "php artisan cache:clear\n";
echo "php artisan config:clear\n";
echo "php artisan view:clear\n"; 
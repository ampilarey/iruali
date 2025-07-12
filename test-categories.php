<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Category;
use App\Http\Controllers\CategoryController;

echo "🧪 Category Controller Test\n";
echo "==========================\n\n";

// Test CategoryController index method
try {
    $controller = new CategoryController();
    $categories = Category::with(['products' => function ($query) {
        $query->where('is_active', true)->where('stock_quantity', '>', 0);
    }])
    ->where('status', 'active')
    ->whereNull('parent_id')
    ->get();

    echo "✅ Categories loaded by controller: " . $categories->count() . "\n\n";
    
    foreach ($categories as $category) {
        echo "📂 {$category->name} ({$category->slug})\n";
        echo "   Products: " . $category->products->count() . "\n";
        echo "   Status: {$category->status}\n";
        echo "   Parent ID: " . ($category->parent_id ?? 'NULL') . "\n\n";
    }

} catch (\Exception $e) {
    echo "❌ Error loading categories: " . $e->getMessage() . "\n";
}

// Test view rendering
echo "🎨 Testing view rendering...\n";
try {
    $view = view('categories.index', compact('categories'));
    echo "✅ View compiled successfully\n";
    echo "📄 View content length: " . strlen($view->render()) . " characters\n";
} catch (\Exception $e) {
    echo "❌ Error rendering view: " . $e->getMessage() . "\n";
}

echo "\n🎯 If categories load here but not on website:\n";
echo "1. Clear browser cache\n";
echo "2. Check if CDN is caching the page\n";
echo "3. Verify the route is working: curl -I http://yourdomain.com/categories\n"; 
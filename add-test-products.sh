#!/bin/bash

# Add Test Products Script
# This script adds test products only if none exist

echo "📦 Adding test products..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script in your Laravel project root."
    exit 1
fi

# Check if products already exist
PRODUCT_COUNT=$(php artisan tinker --execute="echo App\Models\Product::count();" 2>/dev/null | tail -1)

echo "Current products: $PRODUCT_COUNT"

if [ "$PRODUCT_COUNT" -eq 0 ]; then
    echo "✅ No products found. Adding test products..."
    php artisan db:seed --class=ProductSeeder --force
    echo "✅ Test products added successfully!"
else
    echo "⚠️ Products already exist. Skipping to avoid duplicates."
    echo ""
    echo "If you want to add more products, you can:"
    echo "  1. Add them manually through the admin panel"
    echo "  2. Create a custom seeder for additional products"
    echo "  3. Reset the database: php artisan migrate:fresh --seed"
fi

echo ""
echo "🎉 Product addition completed!" 
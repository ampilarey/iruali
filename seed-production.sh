#!/bin/bash

# iruali Production Seeding Script
# This script safely seeds the database without causing duplicate entry errors

echo "🌱 Starting production database seeding..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script in your Laravel project root."
    exit 1
fi

# Check if users already exist
echo "📊 Checking existing data..."
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | tail -1)
PRODUCT_COUNT=$(php artisan tinker --execute="echo App\Models\Product::count();" 2>/dev/null | tail -1)
CATEGORY_COUNT=$(php artisan tinker --execute="echo App\Models\Category::count();" 2>/dev/null | tail -1)

echo "Current data:"
echo "  - Users: $USER_COUNT"
echo "  - Products: $PRODUCT_COUNT"
echo "  - Categories: $CATEGORY_COUNT"

# Only seed if no data exists
if [ "$USER_COUNT" -eq 0 ] && [ "$PRODUCT_COUNT" -eq 0 ] && [ "$CATEGORY_COUNT" -eq 0 ]; then
    echo "✅ Database is empty. Running full seeding..."
    
    # Run seeders in order
    echo "🔐 Seeding permissions..."
    php artisan db:seed --class=PermissionSeeder --force
    
    echo "👥 Seeding roles..."
    php artisan db:seed --class=RoleSeeder --force
    
    echo "👤 Seeding users..."
    php artisan db:seed --class=UserSeeder --force
    
    echo "📂 Seeding categories..."
    php artisan db:seed --class=CategorySeeder --force
    
    echo "📦 Seeding products..."
    php artisan db:seed --class=ProductSeeder --force
    
    echo "🖼️ Seeding banners..."
    php artisan db:seed --class=BannerSeeder --force
    
    echo "🏝️ Seeding islands..."
    php artisan db:seed --class=IslandSeeder --force
    
    echo "✅ All seeders completed successfully!"
    
else
    echo "⚠️ Database already contains data. Skipping seeding to avoid duplicates."
    echo ""
    echo "If you want to reset the database and seed fresh data, run:"
    echo "  php artisan migrate:fresh --seed"
    echo ""
    echo "⚠️ WARNING: This will DELETE ALL existing data!"
fi

echo ""
echo "🎉 Production seeding completed!" 
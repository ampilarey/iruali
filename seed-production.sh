#!/bin/bash

# iruali Production Seeding Script
# This script safely seeds the database without causing duplicate entry errors

echo "🌱 Starting production database seeding..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script in your Laravel project root."
    exit 1
fi

# Check existing data counts
echo "📊 Checking existing data..."
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | tail -1)
ADMIN_COUNT=$(php artisan tinker --execute="echo App\Models\User::where('email', 'admin@example.com')->count();" 2>/dev/null | tail -1)
PRODUCT_COUNT=$(php artisan tinker --execute="echo App\Models\Product::count();" 2>/dev/null | tail -1)
CATEGORY_COUNT=$(php artisan tinker --execute="echo App\Models\Category::count();" 2>/dev/null | tail -1)
PERMISSION_COUNT=$(php artisan tinker --execute="echo App\Models\Permission::count();" 2>/dev/null | tail -1)
ROLE_COUNT=$(php artisan tinker --execute="echo App\Models\Role::count();" 2>/dev/null | tail -1)
BANNER_COUNT=$(php artisan tinker --execute="echo App\Models\Banner::count();" 2>/dev/null | tail -1)
ISLAND_COUNT=$(php artisan tinker --execute="echo App\Models\Island::count();" 2>/dev/null | tail -1)

echo "Current data:"
echo "  - Users: $USER_COUNT (Admin: $ADMIN_COUNT)"
echo "  - Products: $PRODUCT_COUNT"
echo "  - Categories: $CATEGORY_COUNT"
echo "  - Permissions: $PERMISSION_COUNT"
echo "  - Roles: $ROLE_COUNT"
echo "  - Banners: $BANNER_COUNT"
echo "  - Islands: $ISLAND_COUNT"

# Seed permissions if none exist
if [ "$PERMISSION_COUNT" -eq 0 ]; then
    echo "🔐 Seeding permissions..."
    php artisan db:seed --class=PermissionSeeder --force
else
    echo "✅ Permissions already exist, skipping..."
fi

# Seed roles if none exist
if [ "$ROLE_COUNT" -eq 0 ]; then
    echo "👥 Seeding roles..."
    php artisan db:seed --class=RoleSeeder --force
else
    echo "✅ Roles already exist, skipping..."
fi

# Seed admin user if it doesn't exist
if [ "$ADMIN_COUNT" -eq 0 ]; then
    echo "👤 Seeding admin user..."
    php artisan db:seed --class=UserSeeder --force
else
    echo "✅ Admin user already exists, skipping..."
fi

# Seed categories if none exist
if [ "$CATEGORY_COUNT" -eq 0 ]; then
    echo "📂 Seeding categories..."
    php artisan db:seed --class=CategorySeeder --force
else
    echo "✅ Categories already exist, skipping..."
fi

# Seed products if none exist
if [ "$PRODUCT_COUNT" -eq 0 ]; then
    echo "📦 Seeding products..."
    php artisan db:seed --class=ProductSeeder --force
else
    echo "✅ Products already exist, skipping..."
fi

# Seed banners if none exist
if [ "$BANNER_COUNT" -eq 0 ]; then
    echo "🖼️ Seeding banners..."
    php artisan db:seed --class=BannerSeeder --force
else
    echo "✅ Banners already exist, skipping..."
fi

# Seed islands if none exist
if [ "$ISLAND_COUNT" -eq 0 ]; then
    echo "🏝️ Seeding islands..."
    php artisan db:seed --class=IslandSeeder --force
else
    echo "✅ Islands already exist, skipping..."
fi

echo ""
echo "🎉 Production seeding completed!"
echo ""
echo "📋 Summary:"
echo "  - Database is now ready for production use"
echo "  - Admin user: admin@example.com"
echo "  - Password: password"
echo ""
echo "🔗 You can now access your website!" 
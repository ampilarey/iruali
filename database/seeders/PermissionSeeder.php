<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'description' => 'Can view user list', 'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'description' => 'Can create new users', 'group' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'description' => 'Can edit user information', 'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'description' => 'Can delete users', 'group' => 'users'],
            
            // Product Management
            ['name' => 'products.view', 'display_name' => 'View Products', 'description' => 'Can view products', 'group' => 'products'],
            ['name' => 'products.create', 'display_name' => 'Create Products', 'description' => 'Can create new products', 'group' => 'products'],
            ['name' => 'products.edit', 'display_name' => 'Edit Products', 'description' => 'Can edit products', 'group' => 'products'],
            ['name' => 'products.delete', 'display_name' => 'Delete Products', 'description' => 'Can delete products', 'group' => 'products'],
            ['name' => 'products.approve', 'display_name' => 'Approve Products', 'description' => 'Can approve products for sale', 'group' => 'products'],
            
            // Order Management
            ['name' => 'orders.view', 'display_name' => 'View Orders', 'description' => 'Can view orders', 'group' => 'orders'],
            ['name' => 'orders.create', 'display_name' => 'Create Orders', 'description' => 'Can create orders', 'group' => 'orders'],
            ['name' => 'orders.edit', 'display_name' => 'Edit Orders', 'description' => 'Can edit orders', 'group' => 'orders'],
            ['name' => 'orders.delete', 'display_name' => 'Delete Orders', 'description' => 'Can delete orders', 'group' => 'orders'],
            ['name' => 'orders.process', 'display_name' => 'Process Orders', 'description' => 'Can process and fulfill orders', 'group' => 'orders'],
            
            // Category Management
            ['name' => 'categories.view', 'display_name' => 'View Categories', 'description' => 'Can view categories', 'group' => 'categories'],
            ['name' => 'categories.create', 'display_name' => 'Create Categories', 'description' => 'Can create categories', 'group' => 'categories'],
            ['name' => 'categories.edit', 'display_name' => 'Edit Categories', 'description' => 'Can edit categories', 'group' => 'categories'],
            ['name' => 'categories.delete', 'display_name' => 'Delete Categories', 'description' => 'Can delete categories', 'group' => 'categories'],
            
            // Role & Permission Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'description' => 'Can view roles', 'group' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'description' => 'Can create roles', 'group' => 'roles'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'description' => 'Can edit roles', 'group' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'description' => 'Can delete roles', 'group' => 'roles'],
            
            // Seller Management
            ['name' => 'sellers.view', 'display_name' => 'View Sellers', 'description' => 'Can view sellers', 'group' => 'sellers'],
            ['name' => 'sellers.approve', 'display_name' => 'Approve Sellers', 'description' => 'Can approve seller registrations', 'group' => 'sellers'],
            ['name' => 'sellers.suspend', 'display_name' => 'Suspend Sellers', 'description' => 'Can suspend sellers', 'group' => 'sellers'],
            
            // Reports & Analytics
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'description' => 'Can view reports and analytics', 'group' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'description' => 'Can export reports', 'group' => 'reports'],
            
            // Settings
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'description' => 'Can view system settings', 'group' => 'settings'],
            ['name' => 'settings.edit', 'display_name' => 'Edit Settings', 'description' => 'Can edit system settings', 'group' => 'settings'],
            
            // Promotions
            ['name' => 'promotions.view', 'display_name' => 'View Promotions', 'description' => 'Can view promotions', 'group' => 'promotions'],
            ['name' => 'promotions.create', 'display_name' => 'Create Promotions', 'description' => 'Can create promotions', 'group' => 'promotions'],
            ['name' => 'promotions.edit', 'display_name' => 'Edit Promotions', 'description' => 'Can edit promotions', 'group' => 'promotions'],
            ['name' => 'promotions.delete', 'display_name' => 'Delete Promotions', 'description' => 'Can delete promotions', 'group' => 'promotions'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}

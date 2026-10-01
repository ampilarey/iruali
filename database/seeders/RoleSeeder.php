<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system administrator with all permissions',
                'is_default' => false,
            ],
            [
                'name' => 'seller',
                'display_name' => 'Seller',
                'description' => 'Vendor who can manage their own products and orders',
                'is_default' => false,
            ],
            [
                'name' => 'sub_admin',
                'display_name' => 'Sub Administrator',
                'description' => 'Limited administrator with specific permissions',
                'is_default' => false,
            ],
            [
                'name' => 'customer',
                'display_name' => 'Customer',
                'description' => 'Regular customer with basic shopping permissions',
                'is_default' => true,
            ],
            // Staff roles: what each may open in /admin is in config/staff.php
            [
                'name' => 'support',
                'display_name' => 'Support',
                'description' => 'Customer support: orders, returns, moderation, users (read-only)',
                'is_default' => false,
            ],
            [
                'name' => 'finance',
                'display_name' => 'Finance',
                'description' => 'Finance: shop payouts, refunds, analytics, errors and the audit log',
                'is_default' => false,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}

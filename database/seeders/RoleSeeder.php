<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

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
                'is_default' => false
            ],
            [
                'name' => 'seller',
                'display_name' => 'Seller',
                'description' => 'Vendor who can manage their own products and orders',
                'is_default' => false
            ],
            [
                'name' => 'sub_admin',
                'display_name' => 'Sub Administrator',
                'description' => 'Limited administrator with specific permissions',
                'is_default' => false
            ],
            [
                'name' => 'customer',
                'display_name' => 'Customer',
                'description' => 'Regular customer with basic shopping permissions',
                'is_default' => true
            ]
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}

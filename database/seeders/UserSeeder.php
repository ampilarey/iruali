<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            $admin = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'phone' => '7770000',
                'status' => 'active',
                'email_verified' => true,
                'phone_verified' => true,
                'is_active' => true,
                'preferred_language' => 'en',
            ]);

            $this->command->info('✅ Admin user created successfully');
        } else {
            $this->command->info('✅ Admin user already exists, skipping creation');
        }

        // Attach admin role (looked up by name; IDs are not guaranteed)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            if (! $admin->roles()->where('roles.id', $adminRole->id)->exists()) {
                $admin->roles()->attach($adminRole->id);
                $this->command->info('✅ Admin role attached to user');
            } else {
                $this->command->info('✅ Admin role already attached to user');
            }
        }
    }
}

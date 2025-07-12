<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $admin = User::where('email', 'admin@example.com')->first();
        
        if (!$admin) {
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
        
        // Attach admin role if roles are seeded and user doesn't have it
        if (method_exists($admin, 'roles') && !$admin->roles()->where('id', 1)->exists()) {
            $admin->roles()->attach(1); // Assuming admin role is ID 1
            $this->command->info('✅ Admin role attached to user');
        }
    }
}

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
        // On production the admin login comes from .env; the demo login exists only for local work.
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');
        if (! $password) {
            if (app()->isProduction()) {
                throw new \RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding the admin on production.');
            }
            $password = 'password';
        }

        $admin = User::where('email', $email)->first();

        if (! $admin) {
            $admin = User::create([
                'name' => 'Admin User',
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
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

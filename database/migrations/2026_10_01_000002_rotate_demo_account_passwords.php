<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The demo seeder gave every *@example.com shop the password "password". On any server that is not a
 * developer's machine, replace those with random passwords so nobody can sign in as a demo shop.
 * The demo admin is left alone so the owner isn't locked out; change its password from My Account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('local', 'testing')) {
            return;
        }

        DB::table('users')
            ->where('email', 'like', '%@example.com')
            ->where('email', '!=', 'admin@example.com')
            ->orderBy('id')
            ->each(fn ($user) => DB::table('users')->where('id', $user->id)->update(['password' => Hash::make(Str::random(40)), 'remember_token' => null]));
    }

    public function down(): void
    {
        // Passwords can't be restored.
    }
};

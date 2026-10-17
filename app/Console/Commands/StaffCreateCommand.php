<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\ShopStaff;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Make someone iruali staff from the server (the first admin, or when nobody can open the admin).
 * An existing account gets the role; a new one is created and asks for a password here (typed,
 * never shown or saved in the shell history), or, without one, they use "Forgot password".
 *
 *   php artisan staff:create aisha@iruali.mv --role=support --name="Aisha"
 */
class StaffCreateCommand extends Command
{
    protected $signature = 'staff:create {email} {--role=admin : admin, support, finance or catalogue} {--name= : name for a new account}';

    protected $description = 'Make an account iruali staff (creating it if needed)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->option('role');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not an email address.');

            return self::INVALID;
        }
        if (! in_array($role, (array) config('staff.roles'), true)) {
            $this->error('The role must be one of: '.implode(', ', (array) config('staff.roles')).'.');

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        if ($user && ($user->hasRole('seller') || $user->is_seller || ShopStaff::where('user_id', $user->id)->exists())) {
            $this->error('This account has a shop or works for one, so it cannot be iruali staff. Use another email.');

            return self::FAILURE;
        }

        if (! $user) {
            $password = $this->input->isInteractive() ? (string) $this->secret('Password for the new account (leave empty to use "Forgot password" instead)') : '';
            if ($password !== '' && Validator::make(['password' => $password], ['password' => Password::min(8)->letters()->mixedCase()->numbers()->symbols()])->fails()) {
                $this->error('The password needs at least 8 characters with upper and lower case letters, a number and a symbol.');

                return self::INVALID;
            }
            $user = new User(['name' => $this->option('name') ?: strstr($email, '@', true), 'email' => $email]);
            $user->forceFill(['password' => Hash::make($password !== '' ? $password : bin2hex(random_bytes(24))), 'email_verified_at' => now()])->save();
            $this->info('Account created for '.$email.'.');
            if ($password === '') {
                $this->line('They set a password with "Forgot password" on the sign-in page.');
            }
        }

        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id]);
        Audit::record('staff.created', $user, ['role' => $role, 'by' => 'command']);
        $this->info($user->email.' is now '.$role.' staff. They are asked to turn on two-step sign-in if staff must use it.');

        return self::SUCCESS;
    }
}

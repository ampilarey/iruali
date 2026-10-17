<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use App\Support\StaffLockout;
use Illuminate\Console\Command;

/**
 * Let a staff account locked after wrong sign-in tries sign in again now.
 */
class StaffUnlockCommand extends Command
{
    protected $signature = 'staff:unlock {email}';

    protected $description = 'Unlock a staff account locked after wrong sign-in tries';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account has that email.');

            return self::FAILURE;
        }
        StaffLockout::unlock($user);
        Audit::record('staff.unlocked', $user, ['by' => 'command']);
        $this->info($user->email.' can sign in again.');

        return self::SUCCESS;
    }
}

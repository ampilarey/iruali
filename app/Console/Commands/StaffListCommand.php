<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StaffAccess;
use App\Support\StaffLockout;
use Illuminate\Console\Command;

/**
 * Every iruali staff account with its role, two-step sign-in and last sign-in.
 */
class StaffListCommand extends Command
{
    protected $signature = 'staff:list';

    protected $description = 'List iruali staff accounts';

    public function handle(): int
    {
        $staff = User::whereHas('roles', fn ($q) => $q->whereIn('name', (array) config('staff.roles')))->orderBy('email')->get();
        if ($staff->isEmpty()) {
            $this->warn('No staff accounts. Create one with: php artisan staff:create email --role=admin');

            return self::SUCCESS;
        }

        $this->table(['Email', 'Name', 'Role', 'Two-step', 'Active', 'Last sign-in', 'Locked until'], $staff->map(fn (User $user) => [
            $user->email, $user->name, implode(', ', StaffAccess::staffRoles($user)),
            $user->isTwoFactorEnabled() ? 'on' : 'off', $user->is_active ? 'yes' : 'no',
            $user->last_login_at?->format('Y-m-d H:i') ?? '-', StaffLockout::lockedUntil($user)?->format('H:i') ?? '-',
        ])->all());

        return self::SUCCESS;
    }
}

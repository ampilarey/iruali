<?php

namespace App\Console\Commands;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * The "Staff must use two-step sign-in" switch from the server, for when nobody can open
 * Admin → Settings (a staff account without two-step sign-in is sent to set it up first).
 *
 *   php artisan staff:two-factor        # show it
 *   php artisan staff:two-factor off    # optional for staff
 *   php artisan staff:two-factor on     # required again
 */
class StaffTwoFactorCommand extends Command
{
    protected $signature = 'staff:two-factor {state? : on or off}';

    protected $description = 'Show, or switch on or off, the rule that staff must use two-step sign-in';

    public function handle(): int
    {
        $state = $this->argument('state');
        if ($state !== null) {
            if (! in_array($state, ['on', 'off'], true)) {
                $this->error('Use on or off.');

                return self::INVALID;
            }
            Setting::set(['staff_require_two_factor' => $state === 'on' ? 1 : 0]);
            Audit::record('settings.staff_two_factor', null, ['required' => $state === 'on', 'by' => 'command']);
        }

        if (RequireTwoFactor::required()) {
            $this->info('Staff must use two-step sign-in.');
        } elseif (! config('staff.require_two_factor', true)) {
            $this->info('Two-step sign-in is optional for staff (STAFF_REQUIRE_2FA=false in .env).');
        } else {
            $this->info('Two-step sign-in is optional for staff.');
        }
        $this->line('Staff who have it on are still asked for the code; they can turn it off in My Account → Security while it is optional.');

        return self::SUCCESS;
    }
}

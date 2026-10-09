<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Makes a copy of the production database safe to use on the staging site: every non-staff
 * user gets a made-up name, an @example.test email, a fake phone and a new random password;
 * their tokens, 2FA secrets, order addresses, return notes, newsletter emails and OTPs go too.
 * Staff accounts (admin, support, finance) are kept so the team can still sign in.
 */
class AnonymiseCommand extends Command
{
    protected $signature = 'iruali:anonymise
                            {--force : Actually change the data (without it, only shows what would happen)}
                            {--i-know-this-is-production : Required on a production install}';

    protected $description = 'Replace customer and seller personal data with fake values (for the staging site)';

    /** A small built-in list; name = first + last picked by user id, so re-runs are stable. */
    public const FIRST_NAMES = ['Aishath', 'Mohamed', 'Fathimath', 'Ahmed', 'Mariyam', 'Ibrahim', 'Hawwa', 'Ali', 'Aminath', 'Hassan', 'Khadheeja', 'Hussain', 'Zulaikha', 'Abdulla', 'Shifa', 'Adam'];

    public const LAST_NAMES = ['Rasheed', 'Waheed', 'Naseer', 'Shareef', 'Latheef', 'Manik', 'Didi', 'Fulhu', 'Saeed', 'Zahir', 'Nazim', 'Ibrahim', 'Hameed', 'Shakir', 'Riyaz', 'Moosa'];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('i-know-this-is-production')) {
            $this->error('This is a production install (APP_ENV=production). Refusing: this command destroys customer data. Run it on the staging copy, or pass --i-know-this-is-production if you really mean it.');

            return self::FAILURE;
        }

        $staffIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('roles.name', config('staff.roles', ['admin']))
            ->pluck('user_roles.user_id')->unique()->values();

        $users = DB::table('users')->whereNotIn('id', $staffIds);
        $plan = [
            'users anonymised' => $users->count(),
            'staff accounts kept' => $staffIds->count(),
            'personal access tokens deleted' => DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereNotIn('tokenable_id', $staffIds)->count(),
            'order addresses rewritten' => DB::table('orders')->count(),
            'return request notes cleared' => DB::table('return_requests')->count(),
            'newsletter emails replaced' => DB::table('newsletter_subscribers')->count(),
            'OTP rows truncated' => DB::table('otps')->count(),
            'shop owners\' ID card numbers replaced' => DB::table('seller_verifications')->count(),
        ];

        if (! $this->option('force')) {
            $this->table(['What would change', 'Rows'], collect($plan)->map(fn ($n, $k) => [$k, $n])->values()->all());
            $this->warn('Dry run: nothing changed. Add --force to anonymise.');

            return self::FAILURE;
        }

        // Users: name, email, phone, password, tokens, 2FA, address, bank account
        $users->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'name' => self::fakeName($row->id),
                    'email' => "user{$row->id}@example.test",
                    'phone' => (string) (7000000 + $row->id),
                    'password' => Hash::make(Str::random(32)),
                    'remember_token' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_enabled' => false,
                    'avatar' => null,
                    'address' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'date_of_birth' => null,
                    'last_login_ip' => null,
                    'payout_account_number' => null,
                    'payout_account_name' => null,
                ]);
            }
        });

        DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereNotIn('tokenable_id', $staffIds)->delete();

        // Orders: where things were delivered and the phone on the parcel
        DB::table('orders')->orderBy('id')->select('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('orders')->where('id', $row->id)->update([
                    'shipping_address' => json_encode('Test address '.$row->id), // the column is JSON-cast; orders store a string in it
                    'shipping_city' => 'Malé',
                    'shipping_phone' => (string) (7000000 + $row->id),
                ]);
            }
        });

        DB::table('return_requests')->update(['details' => null, 'admin_note' => null]);

        DB::table('newsletter_subscribers')->orderBy('id')->select('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('newsletter_subscribers')->where('id', $row->id)->update(['email' => "subscriber{$row->id}@example.test"]);
            }
        });

        DB::table('otps')->delete(); // delete, not truncate: truncate would commit a surrounding transaction

        // Business verification: the shop owner's ID card number (stored encrypted, with this install's key)
        DB::table('seller_verifications')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('seller_verifications')->where('id', $row->id)->update(['national_id_number' => \Illuminate\Support\Facades\Crypt::encryptString('A'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT))]);
            }
        });

        $this->table(['Changed', 'Rows'], collect($plan)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info('Done. Anonymised users can no longer sign in (random passwords); staff accounts are unchanged.');

        return self::SUCCESS;
    }

    public static function fakeName(int $id): string
    {
        return self::FIRST_NAMES[$id % count(self::FIRST_NAMES)].' '.self::LAST_NAMES[intdiv($id, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];
    }
}

<?php

namespace App\Support;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\DemoDataService;
use App\Services\ManualPaymentService;
use App\Services\Sms\SmsManager;
use App\Services\SocialLoginService;
use Throwable;

/**
 * The launch checklist on the admin dashboard: the business side of going live, next to the
 * technical System status (ReadyChecks). Each line is pass / warn / fail with what to do and
 * where (a link in the admin). Admins only.
 *
 * @phpstan-type LaunchCheck array{name: string, status: string, detail: string, fix: string, link: ?string}
 */
class LaunchChecks
{
    /** @return list<LaunchCheck> */
    public function run(): array
    {
        $checks = [];
        foreach ([
            'Sample data removed' => 'sampleData',
            'Company details' => 'company',
            'Shops and products' => 'shops',
            'Payment methods' => 'payments',
            'SMS for sign-in codes' => 'sms',
            'Live site settings' => 'environment',
            'Error alerts email' => 'alerts',
            'Staff two-step sign-in' => 'staffTwoFactor',
            'Delivery fees' => 'deliveryFees',
            'Google / Facebook / Apple sign-in' => 'socialSignIn',
        ] as $name => $method) {
            try {
                $checks[] = ['name' => $name] + $this->$method();
            } catch (Throwable $e) {
                report($e);
                $checks[] = ['name' => $name, 'status' => 'fail', 'detail' => 'Could not be checked: '.class_basename($e), 'fix' => 'See Admin → Errors.', 'link' => null];
            }
        }

        return $checks;
    }

    /** @param  list<LaunchCheck>  $checks */
    public static function counts(array $checks): array
    {
        $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($checks as $check) {
            $counts[$check['status']] = ($counts[$check['status']] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return array{status: string, detail: string, fix: string, link: ?string} */
    protected function result(string $status, string $detail, string $fix = '', ?string $link = null): array
    {
        return ['status' => $status, 'detail' => $detail, 'fix' => $fix, 'link' => $link];
    }

    protected function sampleData(): array
    {
        $status = app(DemoDataService::class)->status();
        if ($status['products_live'] > 0 || $status['shops_open'] > 0) {
            return $this->result('fail', $status['products_live'].' sample product(s) and '.$status['shops_open'].' demo shop(s) are still on the site', 'Remove them in Admin → Sample data (they can be restored).', route('admin.sample-data'));
        }

        return $this->result('pass', 'No sample products or demo shops on the site');
    }

    protected function company(): array
    {
        $missing = Company::missing();

        return $missing
            ? $this->result('fail', 'Missing: '.implode(', ', $missing), 'Fill them in Admin → Settings. They appear on the policy pages and receipts, and BML asks for them.', route('admin.settings'))
            : $this->result('pass', Company::legalName().', '.Company::phone());
    }

    protected function shops(): array
    {
        $demo = DemoData::shopEmails();
        $shops = User::query()->where('is_seller', true)->where('seller_approved', true)->whereNotIn('email', $demo)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))->count();
        $products = Product::query()->where('is_active', true)
            ->whereHas('seller', fn ($q) => $q->where('seller_approved', true)->whereNotIn('email', $demo))->count();

        return $shops === 0
            ? $this->result('fail', 'No approved shop has a live product yet', 'Approve shops in Admin → Sellers, and their products in Admin → Products.', route('admin.sellers'))
            : $this->result('pass', $shops.' shop(s) with '.$products.' live product(s)');
    }

    protected function payments(): array
    {
        $card = PayLink::cardAvailable();
        $transfer = ManualPaymentService::transferEnabled();
        $cod = ManualPaymentService::codEnabled();
        if ($transfer && ManualPaymentService::transferDetails()['number'] === '') {
            return $this->result('fail', 'Bank transfer is on, but iruali\'s account number is empty', 'Add the account in Admin → Settings → Payments.', route('admin.settings'));
        }
        $on = array_keys(array_filter(['card (BML)' => $card, 'bank transfer' => $transfer, 'cash on delivery' => $cod]));
        if ($on === []) {
            return $this->result('fail', 'No way to pay is switched on', 'Add the BML keys to .env (card), or switch on bank transfer or cash on delivery in Admin → Settings.', route('admin.settings'));
        }
        if (! $card) {
            return $this->result('warn', 'On: '.implode(', ', $on).'. Card payment is off', 'Card payment needs BML_API_KEY (and BML_ENVIRONMENT=production) in .env on the server.');
        }
        if (config('services.bml.environment', 'sandbox') !== 'production' && app()->environment('production')) {
            return $this->result('warn', 'On: '.implode(', ', $on).'. BML is still the sandbox', 'Set BML_ENVIRONMENT=production and the live key once BML approves go-live.');
        }

        return $this->result('pass', 'On: '.implode(', ', $on));
    }

    protected function sms(): array
    {
        if (! app(SmsManager::class)->isLive()) {
            return $this->result('fail', 'No SMS gateway: new customers cannot get a sign-in code by text', 'Set the Dhiraagu SMS details in .env on the server, then send a test from Admin → SMS.', route('admin.sms'));
        }
        if (! SmsManager::enabled()) {
            return $this->result('fail', 'SMS is switched off: new customers cannot get a sign-in code by text', 'Switch SMS on in Admin → SMS.', route('admin.sms'));
        }

        return $this->result('pass', 'Sign-in codes go out by text');
    }

    protected function environment(): array
    {
        $problems = [];
        if (config('app.debug')) {
            $problems[] = 'debug mode is on (error pages show technical details)';
        }
        if (! app()->environment('production')) {
            $problems[] = 'APP_ENV is '.app()->environment();
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = 'APP_URL is not https';
        }

        return $problems
            ? $this->result(app()->environment('production') ? 'fail' : 'warn', ucfirst(implode('; ', $problems)), 'On the live site set APP_ENV=production, APP_DEBUG=false and APP_URL=https://iruali.mv in .env, then php artisan config:cache.')
            : $this->result('pass', 'Production, debug off, '.config('app.url'));
    }

    protected function alerts(): array
    {
        $to = ErrorTracker::alertsTo();

        return $to
            ? $this->result('pass', 'Error alerts and the daily digest go to '.$to)
            : $this->result('warn', 'Nobody is told about errors', 'Set ALERTS_EMAIL in .env, or a contact email in Admin → Settings.', route('admin.settings'));
    }

    protected function staffTwoFactor(): array
    {
        return RequireTwoFactor::required()
            ? $this->result('pass', 'Staff need an authenticator code to open the admin')
            : $this->result('warn', 'Staff can open the admin with only a password', 'Switch "Require two-step sign-in for staff" on in Admin → Settings.', route('admin.settings'));
    }

    protected function deliveryFees(): array
    {
        $male = (float) Setting::get('delivery_fee_greater_male');
        $islands = (float) Setting::get('delivery_fee_islands');

        return $male > 0 && $islands > 0
            ? $this->result('pass', 'Greater Malé '.Money::format($male).', other islands '.Money::format($islands))
            : $this->result('warn', 'A delivery fee is 0 (Greater Malé '.Money::format($male).', other islands '.Money::format($islands).')', 'Check the fees in Admin → Delivery, unless free delivery is intended.', route('admin.delivery'));
    }

    protected function socialSignIn(): array
    {
        $on = array_map(fn ($provider) => $provider->label(), app(SocialLoginService::class)->enabled());

        return $on
            ? $this->result('pass', 'On: '.implode(', ', $on))
            : $this->result('warn', 'Off (optional): customers sign in with their mobile number or email', 'To offer it, add the Google / Facebook / Apple keys to .env (see docs).');
    }
}

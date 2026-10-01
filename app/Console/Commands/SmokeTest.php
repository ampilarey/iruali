<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Support\SmokeChecks;
use App\Support\SmokeFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Post-deploy smoke test. Run by scripts/deploy-production.sh and scripts/pull-deploy-test.sh,
 * and by hand after anything that touches the server.
 */
class SmokeTest extends Command
{
    protected $signature = 'iruali:smoke
                            {--base-url= : Site to test (default APP_URL)}
                            {--place-order : Sign in as the smoke user, place and cancel a real order}
                            {--setup : Create or update the smoke customer from SMOKE_USER_EMAIL / SMOKE_USER_PASSWORD}';

    protected $description = 'Smoke test a running site: key pages, health, and optionally one order placed and cancelled';

    public function handle(SmokeFetcher $fetcher): int
    {
        if ($this->option('setup')) {
            return $this->setup();
        }

        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        if ($baseUrl === '') {
            $this->error('No base URL: pass --base-url or set APP_URL.');

            return self::FAILURE;
        }

        $order = null;
        if ($this->option('place-order')) {
            $order = $this->orderTarget();
            if ($order === null) {
                return self::FAILURE;
            }
        }

        $results = (new SmokeChecks(
            fetcher: $fetcher,
            baseUrl: $baseUrl,
            order: $order,
            stockReader: fn (int $productId, ?int $variantId): int => $variantId
                ? (int) ProductVariant::whereKey($variantId)->value('stock_quantity')
                : (int) Product::whereKey($productId)->value('stock_quantity'),
        ))->run();

        $this->line("Smoke test against {$baseUrl}");
        $this->table(
            ['Check', 'Status', 'Detail'],
            array_map(fn ($r) => [$r['name'], strtoupper($r['status']), $r['detail']], $results),
        );

        $failures = SmokeChecks::failures($results);
        if ($failures > 0) {
            $this->error("SMOKE FAILED ({$failures} failures)");

            return self::FAILURE;
        }

        $this->info('SMOKE OK');

        return self::SUCCESS;
    }

    /**
     * The smoke customer and the cheapest active product (its cheapest in-stock variant when it has them).
     *
     * @return array{email: string, password: string, product_id: int, variant_id: int|null}|null
     */
    protected function orderTarget(): ?array
    {
        $email = (string) config('services.smoke.email');
        $password = (string) config('services.smoke.password');
        if ($email === '' || $password === '') {
            $this->error('--place-order needs SMOKE_USER_EMAIL and SMOKE_USER_PASSWORD in .env (then run iruali:smoke --setup once).');

            return null;
        }
        if (! User::where('email', $email)->where('is_smoke_test', true)->exists()) {
            $this->error("No smoke customer {$email}: run php artisan iruali:smoke --setup first.");

            return null;
        }

        $product = Product::query()->where('is_active', true)
            ->where(function ($q) {
                $q->where('stock_quantity', '>', 0)
                    ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('stock_quantity', '>', 0));
            })
            ->orderBy('price')->orderBy('id')->first();
        if (! $product) {
            $this->error('No active product with stock to order.');

            return null;
        }

        $variant = $product->variants()->where('is_active', true)->where('stock_quantity', '>', 0)->orderBy('price')->orderBy('id')->first();

        return [
            'email' => $email,
            'password' => $password,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
        ];
    }

    /**
     * Create (or refresh) the smoke customer. Flagged is_smoke_test so it is left out of analytics,
     * rewards and emails.
     */
    protected function setup(): int
    {
        $email = (string) config('services.smoke.email');
        $password = (string) config('services.smoke.password');
        if ($email === '' || $password === '') {
            $this->error('Set SMOKE_USER_EMAIL and SMOKE_USER_PASSWORD in .env first.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => 'Smoke Test',
            'password' => Hash::make($password),
            'is_active' => true,
            'is_smoke_test' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'phone' => $user->phone ?? '7000000',
            'country' => $user->country ?? 'Maldives',
        ])->save();
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer'])->id]);

        $this->info("Smoke customer ready: {$email} (user #{$user->id}, is_smoke_test = true)");

        return self::SUCCESS;
    }
}

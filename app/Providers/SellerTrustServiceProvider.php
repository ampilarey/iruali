<?php

namespace App\Providers;

use App\Models\SellerVerification;
use App\Support\AdminInbox;
use Illuminate\Support\ServiceProvider;

/**
 * Shops customers can trust: business verification (Admin → Verifications) and holiday mode.
 * Routes are in routes/web/seller-trust.php.
 */
class SellerTrustServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Admin inbox: shops whose business documents are waiting to be checked
        AdminInbox::register('shops_to_verify', fn () => [
            'label' => 'Shops to verify',
            'count' => SellerVerification::query()->pending()->forShops()->count(),
            'route' => 'admin.verifications',
            'severity' => 'warn',
        ]);
    }
}

<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\Order;
use App\Models\Voucher;
use App\Policies\ProductPolicy;
use App\Policies\OrderPolicy;
use App\Policies\VoucherPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
        Voucher::class => VoucherPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
} 
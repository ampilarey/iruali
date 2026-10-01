<?php

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

/**
 * "Customers can trust it": SMS, order messaging, disputes and delivery tracking.
 */
class TrustServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsManager::class);
    }

    public function boot(): void
    {
        Notification::extend('sms', fn ($app) => new SmsChannel($app->make(SmsManager::class)));
    }
}

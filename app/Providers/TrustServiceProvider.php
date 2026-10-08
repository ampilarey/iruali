<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Dispute;
use App\Notifications\Channels\SmsChannel;
use App\Services\MessagingService;
use App\Services\Sms\SmsManager;
use App\Support\AdminInbox;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
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

        // Unread message badges for the customer menu, the seller tabs and the admin dashboard
        View::composer(['layouts.app', 'seller.partials.header', 'admin.dashboard'], function ($view) {
            $view->with('messageBadges', $this->app->make(MessagingService::class)->unreadCounts(auth()->user()));
        });

        $this->registerAdminInboxCounts();
    }

    /** Open disputes and unread threads in the admin inbox. */
    protected function registerAdminInboxCounts(): void
    {
        AdminInbox::register('disputes', fn () => [
            'label' => 'Open disputes', 'count' => Dispute::open()->count(),
            'route' => 'admin.disputes', 'severity' => 'warn',
        ]);
        AdminInbox::register('messages', fn () => [
            'label' => 'Unread messages', 'count' => (int) Conversation::sum('admin_unread_count'),
            'route' => 'admin.messages', 'severity' => 'info',
        ]);
    }
}

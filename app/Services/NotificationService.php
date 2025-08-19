<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

class NotificationService
{
    /**
     * Flash a success message to the session
     */
    public static function success(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'success',
            'title' => $title ?? __('app.success'),
            'message' => $message,
            'icon' => 'success'
        ]);
    }

    /**
     * Flash an error message to the session
     */
    public static function error(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'error',
            'title' => $title ?? __('app.error'),
            'message' => $message,
            'icon' => 'error'
        ]);
    }

    /**
     * Flash a warning message to the session
     */
    public static function warning(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'warning',
            'title' => $title ?? __('app.warning'),
            'message' => $message,
            'icon' => 'warning'
        ]);
    }

    /**
     * Flash an info message to the session
     */
    public static function info(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'info',
            'title' => $title ?? __('app.info'),
            'message' => $message,
            'icon' => 'info'
        ]);
    }

    /**
     * Flash a question/confirmation message to the session
     */
    public static function question(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'question',
            'title' => $title ?? __('app.confirm'),
            'message' => $message,
            'icon' => 'question'
        ]);
    }

    /**
     * Get the current notification from session
     */
    public static function getNotification(): ?array
    {
        return Session::get('notification');
    }

    /**
     * Clear the current notification from session
     */
    public static function clear(): void
    {
        Session::forget('notification');
    }

    /**
     * Flash multiple notifications at once
     */
    public static function multiple(array $notifications): void
    {
        Session::flash('notifications', $notifications);
    }

    /**
     * Get multiple notifications from session
     */
    public static function getMultipleNotifications(): array
    {
        return Session::get('notifications', []);
    }

    /**
     * Clear multiple notifications from session
     */
    public static function clearMultiple(): void
    {
        Session::forget('notifications');
    }

    /**
     * Create a notification for common actions
     */
    public static function created(string $modelName): void
    {
        self::success(__('app.created_successfully', ['model' => $modelName]));
    }

    public static function updated(string $modelName): void
    {
        self::success(__('app.updated_successfully', ['model' => $modelName]));
    }

    public static function deleted(string $modelName): void
    {
        self::success(__('app.deleted_successfully', ['model' => $modelName]));
    }

    public static function addedToCart(string $productName): void
    {
        self::success(__('app.added_to_cart_successfully', ['product' => $productName]));
    }

    public static function removedFromCart(string $productName): void
    {
        self::success(__('app.removed_from_cart_successfully', ['product' => $productName]));
    }

    public static function addedToWishlist(string $productName): void
    {
        self::success(__('app.added_to_wishlist_successfully', ['product' => $productName]));
    }

    public static function removedFromWishlist(string $productName): void
    {
        self::success(__('app.removed_from_wishlist_successfully', ['product' => $productName]));
    }

    public static function orderPlaced(): void
    {
        self::success(__('app.order_placed_successfully'));
    }

    public static function voucherApplied(string $code): void
    {
        self::success(__('app.voucher_applied_successfully', ['code' => $code]));
    }

    public static function voucherRemoved(): void
    {
        self::success(__('app.voucher_removed_successfully'));
    }

    public static function loginSuccess(): void
    {
        self::success(__('auth.login_successful'));
    }

    public static function logoutSuccess(): void
    {
        self::success(__('auth.logout_successful'));
    }

    public static function registrationSuccess(): void
    {
        self::success(__('auth.registration_successful'));
    }

    public static function emailVerified(): void
    {
        self::success(__('auth.email_verified_successfully'));
    }

    public static function phoneVerified(): void
    {
        self::success(__('auth.phone_verified_successfully'));
    }

    public static function twoFactorEnabled(): void
    {
        self::success(__('auth.2fa_enabled'));
    }

    public static function twoFactorDisabled(): void
    {
        self::success(__('auth.2fa_disabled'));
    }
} 
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
            'title' => $title ?? __('Success'),
            'message' => $message,
            'icon' => 'success',
        ]);
    }

    /**
     * Flash an error message to the session
     */
    public static function error(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'error',
            'title' => $title ?? __('Error'),
            'message' => $message,
            'icon' => 'error',
        ]);
    }

    /**
     * Flash a warning message to the session
     */
    public static function warning(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'warning',
            'title' => $title ?? __('Warning'),
            'message' => $message,
            'icon' => 'warning',
        ]);
    }

    /**
     * Flash an info message to the session
     */
    public static function info(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'info',
            'title' => $title ?? __('Information'),
            'message' => $message,
            'icon' => 'info',
        ]);
    }

    /**
     * Flash a question/confirmation message to the session
     */
    public static function question(string $message, ?string $title = null): void
    {
        Session::flash('notification', [
            'type' => 'question',
            'title' => $title ?? __('Confirm'),
            'message' => $message,
            'icon' => 'question',
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
        self::success(__(':model created successfully!', ['model' => $modelName]));
    }

    public static function updated(string $modelName): void
    {
        self::success(__(':model updated successfully!', ['model' => $modelName]));
    }

    public static function deleted(string $modelName): void
    {
        self::success(__(':model deleted successfully!', ['model' => $modelName]));
    }

    public static function addedToCart(string $productName): void
    {
        self::success(__(':product added to cart successfully!', ['product' => $productName]));
    }

    public static function removedFromCart(string $productName): void
    {
        self::success(__(':product removed from cart successfully!', ['product' => $productName]));
    }

    public static function addedToWishlist(string $productName): void
    {
        self::success(__(':product added to wishlist successfully!', ['product' => $productName]));
    }

    public static function removedFromWishlist(string $productName): void
    {
        self::success(__(':product removed from wishlist successfully!', ['product' => $productName]));
    }

    public static function orderPlaced(): void
    {
        self::success(__('Order placed successfully!'));
    }

    public static function voucherApplied(string $code): void
    {
        self::success(__('Voucher :code applied successfully!', ['code' => $code]));
    }

    public static function voucherRemoved(): void
    {
        self::success(__('Voucher removed successfully!'));
    }

    public static function loginSuccess(): void
    {
        self::success(__('Login successful!'));
    }

    public static function logoutSuccess(): void
    {
        self::success(__('Logout successful!'));
    }

    public static function registrationSuccess(): void
    {
        self::success(__('Registration successful! Please verify your email and phone.'));
    }

    public static function emailVerified(): void
    {
        self::success(__('Email verified successfully!'));
    }

    public static function phoneVerified(): void
    {
        self::success(__('Phone verified successfully!'));
    }

    public static function twoFactorEnabled(): void
    {
        self::success(__('Two-factor authentication has been enabled.'));
    }

    public static function twoFactorDisabled(): void
    {
        self::success(__('Two-factor authentication has been disabled.'));
    }
}

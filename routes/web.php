<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LocaleController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware([\App\Http\Middleware\SetLocale::class])->group(function () {
    // Locale switching
    Route::get('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

    // Public routes
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Authentication routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register']);
    });

    // 2FA routes
    Route::middleware('guest')->group(function () {
        Route::get('/2fa', [AuthController::class, 'show2FA'])->name('2fa.show');
        Route::post('/2fa/verify', [AuthController::class, 'verify2FA'])->name('2fa.verify');
    });

    // Verification routes
    Route::middleware('auth')->group(function () {
        Route::get('/verification/notice', [AuthController::class, 'showVerificationNotice'])->name('verification.notice');
        Route::post('/auth/send/email/otp', [AuthController::class, 'sendEmailOTP'])->name('auth.send.email.otp');
        Route::post('/auth/send/sms/otp', [AuthController::class, 'sendSMSOTP'])->name('auth.send.sms.otp');
        Route::post('/auth/verify/email/otp', [AuthController::class, 'verifyEmailOTP'])->name('auth.verify.email.otp');
        Route::post('/auth/verify/phone/otp', [AuthController::class, 'verifyPhoneOTP'])->name('auth.verify.phone.otp');
    });

    // Authenticated user routes
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        // Cart routes
        Route::get('/cart', [CartController::class, 'index'])->name('cart');
        Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
        Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('cart.remove');
        Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
        Route::post('cart/apply-voucher', [\App\Http\Controllers\CartController::class, 'applyVoucher'])->name('cart.applyVoucher');
        Route::post('cart/remove-voucher', [\App\Http\Controllers\CartController::class, 'removeVoucher'])->name('cart.removeVoucher');
        // Wishlist routes
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
        Route::post('/wishlist/add/{product}', [WishlistController::class, 'add'])->name('wishlist.add');
        Route::delete('/wishlist/remove/{product}', [WishlistController::class, 'remove'])->name('wishlist.remove');
        // Checkout routes
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
        Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
        Route::post('checkout/redeem-points', [\App\Http\Controllers\CheckoutController::class, 'redeemPoints'])->name('checkout.redeemPoints');
        Route::post('checkout/remove-points', [\App\Http\Controllers\CheckoutController::class, 'removePoints'])->name('checkout.removePoints');
        // Order routes
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        // 2FA setup routes
        Route::get('/profile/2fa/setup', [AuthController::class, 'show2FASetup'])->name('profile.2fa.setup');
        Route::post('/profile/2fa/enable', [AuthController::class, 'enable2FA'])->name('profile.2fa.enable');
        Route::post('/profile/2fa/disable', [AuthController::class, 'disable2FA'])->name('profile.2fa.disable');
    });

    // Public order tracking
    Route::get('track', [\App\Http\Controllers\OrderTrackingController::class, 'form'])->name('order.track.form');
    Route::post('track', [\App\Http\Controllers\OrderTrackingController::class, 'submit'])->name('order.track.submit');
    Route::get('track/order/{order}', [\App\Http\Controllers\OrderTrackingController::class, 'show'])->name('order.track.show');

    // Seller routes
    Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
        Route::get('/products', [SellerController::class, 'products'])->name('products');
        Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
        Route::get('/profile', [SellerController::class, 'profile'])->name('profile');
        Route::get('/analytics', [SellerController::class, 'analytics'])->name('analytics');
    });

    // Admin routes
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/sellers', [AdminController::class, 'sellers'])->name('sellers');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::resource('vouchers', \App\Http\Controllers\Admin\VoucherController::class)->except(['show']);
    });
});

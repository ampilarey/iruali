<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\LocaleController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\OrderTrackingController;
use App\Http\Controllers\Customer\SearchController;
use App\Http\Controllers\Customer\ShopController;
use App\Http\Controllers\Customer\WishlistController;
use App\Http\Controllers\Seller\SellerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware([\App\Http\Middleware\SetLocale::class])->group(function () {
    // Locale switching
    Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

    // Public routes
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::get('/products', [\App\Http\Controllers\Customer\ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [\App\Http\Controllers\Customer\ProductController::class, 'show'])->name('products.show');
    Route::get('/categories', [\App\Http\Controllers\Customer\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category:slug}', [\App\Http\Controllers\Customer\CategoryController::class, 'show'])->name('categories.show');
    Route::get('/search', [SearchController::class, 'search'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest')->middleware('throttle:60,1');
    Route::get('/brands/{brand}', [ShopController::class, 'brand'])->name('brands.show')->where('brand', '.+');
    Route::get('/deals', [ShopController::class, 'deals'])->name('deals');
    Route::get('/shops/{seller}', [ShopController::class, 'seller'])->name('sellers.show');
    Route::view('/help', 'pages.help')->name('help');
    Route::post('/products/{product}/stock-alert', [\App\Http\Controllers\Customer\StockAlertController::class, 'store'])->name('stock-alerts.store')->middleware('throttle:10,1');
    Route::post('/newsletter', [\App\Http\Controllers\Customer\NewsletterController::class, 'store'])->name('newsletter.store')->middleware('throttle:10,1');
    Route::get('/compare', [\App\Http\Controllers\Customer\CompareController::class, 'index'])->name('compare');
    Route::post('/compare/{product}', [\App\Http\Controllers\Customer\CompareController::class, 'toggle'])->name('compare.toggle');
    Route::delete('/compare', [\App\Http\Controllers\Customer\CompareController::class, 'clear'])->name('compare.clear');

    // Cart: open to guests; checkout asks them to sign in and their cart comes along.
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/add-many', [CartController::class, 'addMany'])->name('cart.addMany');
    Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('cart/apply-voucher', [CartController::class, 'applyVoucher'])->name('cart.applyVoucher');
    Route::post('cart/remove-voucher', [CartController::class, 'removeVoucher'])->name('cart.removeVoucher');
    Route::middleware('auth')->group(function () {
        Route::post('/products/{product}/reviews', [\App\Http\Controllers\Customer\ReviewController::class, 'store'])->name('reviews.store')->middleware('throttle:10,1');
        Route::post('/reviews/{review}/helpful', [\App\Http\Controllers\Customer\ReviewController::class, 'helpful'])->name('reviews.helpful')->middleware('throttle:30,1');
        Route::post('/products/{product}/questions', [\App\Http\Controllers\Customer\QuestionController::class, 'store'])->name('questions.store')->middleware('throttle:10,1');
        Route::post('/questions/{question}/answer', [\App\Http\Controllers\Customer\QuestionController::class, 'answer'])->name('questions.answer');
        Route::post('/cart/{item}/save-for-later', [CartController::class, 'saveForLater'])->name('cart.saveForLater');
        Route::post('/saved/{saved}/move-to-cart', [CartController::class, 'moveToCart'])->name('saved.moveToCart');
        Route::delete('/saved/{saved}', [CartController::class, 'removeSaved'])->name('saved.remove');
    });

    // Authentication routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    });

    // 2FA routes
    Route::middleware('guest')->group(function () {
        Route::get('/2fa', [AuthController::class, 'show2FA'])->name('2fa.show');
        Route::post('/2fa/verify', [AuthController::class, 'verify2FA'])->name('2fa.verify')->middleware('throttle:5,1');
    });

    // Verification routes
    Route::middleware('auth')->group(function () {
        Route::get('/verification/notice', [AuthController::class, 'showVerificationNotice'])->name('verification.notice');
        Route::post('/auth/send/email/otp', [AuthController::class, 'sendEmailOTP'])->name('auth.send.email.otp')->middleware('throttle:3,1');
        Route::post('/auth/send/sms/otp', [AuthController::class, 'sendSMSOTP'])->name('auth.send.sms.otp')->middleware('throttle:3,1');
        Route::post('/auth/verify/email/otp', [AuthController::class, 'verifyEmailOTP'])->name('auth.verify.email.otp')->middleware('throttle:5,1');
        Route::post('/auth/verify/phone/otp', [AuthController::class, 'verifyPhoneOTP'])->name('auth.verify.phone.otp')->middleware('throttle:5,1');
    });

    // Authenticated user routes
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        // Account routes
        Route::get('/account', [AuthController::class, 'account'])->name('account');
        // Cart routes
        // Wishlist routes
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
        Route::post('/wishlist/add/{product}', [WishlistController::class, 'add'])->name('wishlist.add');
        Route::delete('/wishlist/remove/{product}', [WishlistController::class, 'remove'])->name('wishlist.remove');
        Route::delete('/wishlist/clear', [WishlistController::class, 'clear'])->name('wishlist.clear');
        // Checkout routes
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
        Route::post('checkout/redeem-points', [CheckoutController::class, 'redeemPoints'])->name('checkout.redeemPoints');
        Route::post('checkout/remove-points', [CheckoutController::class, 'removePoints'])->name('checkout.removePoints');
        // Order routes
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/buy-again', [OrderController::class, 'buyAgain'])->name('orders.buyAgain');
        Route::post('/orders/{order}/pay', [\App\Http\Controllers\Customer\BmlPaymentController::class, 'pay'])->name('payments.bml.pay')->middleware('throttle:10,1');
        Route::post('/orders/{order}/payment-slip', [OrderController::class, 'uploadPaymentSlip'])->name('orders.payment-slip.store')->middleware('throttle:10,1');
        Route::get('/orders/{order}/payment-slip', [OrderController::class, 'showPaymentSlip'])->name('orders.payment-slip.show');
        // 2FA setup routes
        Route::get('/profile/2fa/setup', [AuthController::class, 'show2FASetup'])->name('profile.2fa.setup');
        Route::post('/profile/2fa/enable', [AuthController::class, 'enable2FA'])->name('profile.2fa.enable');
        Route::post('/profile/2fa/disable', [AuthController::class, 'disable2FA'])->name('profile.2fa.disable');
    });

    // BML sends the customer back here after the payment page (their session may have expired, so no auth)
    Route::get('/payments/bml/return/{order}', [\App\Http\Controllers\Customer\BmlPaymentController::class, 'return'])->name('payments.bml.return')->middleware('throttle:30,1');

    // Public order tracking
    Route::get('track', [OrderTrackingController::class, 'form'])->name('order.track.form');
    Route::post('track', [OrderTrackingController::class, 'submit'])->name('order.track.submit')->middleware('throttle:10,1');
    Route::get('track/order/{order}', [OrderTrackingController::class, 'show'])->name('order.track.show')->middleware('signed');

    // Seller routes
    // Seller application (any logged-in user)
    Route::middleware('auth')->group(function () {
        Route::get('/seller/apply', [\App\Http\Controllers\Seller\ApplicationController::class, 'create'])->name('seller.apply');
        Route::post('/seller/apply', [\App\Http\Controllers\Seller\ApplicationController::class, 'store'])->name('seller.apply.store')->middleware('throttle:5,1');
    });

    Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
        Route::resource('products', \App\Http\Controllers\Seller\ProductController::class)->except(['show']);
        Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [SellerController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/status', [SellerController::class, 'updateOrderStatus'])->name('orders.status');
        Route::get('/profile', [SellerController::class, 'profile'])->name('profile');
        Route::put('/profile', [SellerController::class, 'updateProfile'])->name('profile.update');
        Route::get('/analytics', [SellerController::class, 'analytics'])->name('analytics');
        Route::get('/questions', [SellerController::class, 'questions'])->name('questions');
    });

    // Admin routes
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/sellers', [AdminController::class, 'sellers'])->name('sellers');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AdminController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/status', [AdminController::class, 'updateOrderStatus'])->name('orders.status');
        Route::post('/orders/{order}/payment', [AdminController::class, 'updatePayment'])->name('orders.payment');
        Route::post('/orders/{order}/bml-sync', [AdminController::class, 'syncBmlPayment'])->name('orders.bml-sync');
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/sellers/{seller}/approve', [AdminController::class, 'approveSeller'])->name('sellers.approve');
        Route::post('/sellers/{seller}/reject', [AdminController::class, 'rejectSeller'])->name('sellers.reject');
        Route::post('/products/{product}/approve', [AdminController::class, 'approveProduct'])->name('products.approve');
        Route::resource('vouchers', \App\Http\Controllers\Admin\VoucherController::class)->except(['show']);

        // Moderation: reviews, questions, newsletter signups
        Route::get('/reviews', [\App\Http\Controllers\Admin\ModerationController::class, 'reviews'])->name('reviews');
        Route::post('/reviews/{review}/toggle', [\App\Http\Controllers\Admin\ModerationController::class, 'toggleReview'])->name('reviews.toggle');
        Route::delete('/reviews/{review}', [\App\Http\Controllers\Admin\ModerationController::class, 'destroyReview'])->name('reviews.destroy');
        Route::get('/questions', [\App\Http\Controllers\Admin\ModerationController::class, 'questions'])->name('questions');
        Route::delete('/questions/{question}', [\App\Http\Controllers\Admin\ModerationController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::get('/newsletter', [\App\Http\Controllers\Admin\ModerationController::class, 'newsletter'])->name('newsletter');
        Route::delete('/newsletter/{subscriber}', [\App\Http\Controllers\Admin\ModerationController::class, 'destroySubscriber'])->name('newsletter.destroy');
    });
});

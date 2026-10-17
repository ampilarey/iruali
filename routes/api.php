<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TestDeployWebhookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use App\Support\ApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// BML Connect payment webhooks (signed by BML, checked in the controller)
Route::post('/payments/bml/webhook', [\App\Http\Controllers\Customer\BmlPaymentController::class, 'webhook'])
    ->name('payments.bml.webhook')
    ->middleware('throttle:120,1');

// Public routes
Route::prefix('v1')->group(function () {

    // Authentication routes
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Public product routes (static paths go before /products/{product}, or they are
    // swallowed by the model binding and 404)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/on-sale', [ProductController::class, 'onSale']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Public category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    // Search
    Route::get('/search', [SearchController::class, 'search']);

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('abilities:'.ApiToken::PROFILE_READ)->group(function () {
            Route::get('/user', [UserController::class, 'profile']);
        });
        Route::middleware('abilities:'.ApiToken::PROFILE_WRITE)->group(function () {
            Route::put('/user', [UserController::class, 'updateProfile']);
            Route::post('/user/change-password', [UserController::class, 'changePassword']);
        });

        Route::middleware('abilities:'.ApiToken::CART_WRITE)->group(function () {
            Route::get('/cart', [CartController::class, 'index']);
            Route::post('/cart/add', [CartController::class, 'add']);
            Route::put('/cart/update/{item}', [CartController::class, 'update']);
            Route::delete('/cart/remove/{item}', [CartController::class, 'remove']);
            Route::post('/cart/clear', [CartController::class, 'clear']);
            Route::post('/cart/apply-voucher', [CartController::class, 'applyVoucher']);
            Route::post('/cart/remove-voucher', [CartController::class, 'removeVoucher']);
        });

        Route::middleware('abilities:'.ApiToken::WISHLIST_WRITE)->group(function () {
            Route::get('/wishlist', [WishlistController::class, 'index']);
            Route::post('/wishlist/add', [WishlistController::class, 'add']);
            Route::delete('/wishlist/remove/{product}', [WishlistController::class, 'remove']);
            Route::post('/wishlist/clear', [WishlistController::class, 'clear']);
        });

        Route::middleware('abilities:'.ApiToken::ORDER_READ)->group(function () {
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::get('/orders/{order}/track', [OrderController::class, 'track']);
        });
        Route::middleware('abilities:'.ApiToken::ORDER_WRITE)->group(function () {
            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/checkout/redeem-points', [OrderController::class, 'redeemPoints']);
            Route::post('/checkout/remove-points', [OrderController::class, 'removePoints']);
        });

        Route::post('/products', [ProductController::class, 'store'])->middleware('abilities:'.ApiToken::PRODUCTS_WRITE);
    });
});

// Health check endpoint
Route::get('/health', function () {
    $payload = [
        'status' => 'healthy',
        'timestamp' => now(),
        'version' => '1.0.0',
    ];

    // The deploy stamp is for operators. The public body does not include the commit.
    $token = (string) config('app.health_detail_token');
    $provided = (string) request()->header('X-Health-Token', '');
    if ($token !== '' && $provided !== '' && hash_equals($token, $provided)) {
        $stampPath = storage_path('app/deploy-stamp.json');
        $stamp = is_file($stampPath) ? json_decode((string) file_get_contents($stampPath), true) : null;
        $payload['commit'] = is_array($stamp) ? ($stamp['commit_short'] ?? 'unknown') : 'unknown';
        $payload['branch'] = is_array($stamp) ? ($stamp['branch'] ?? null) : null;
        $payload['tag'] = is_array($stamp) && ($stamp['tag'] ?? '') !== '' ? $stamp['tag'] : null;
        $payload['deployed_at'] = is_array($stamp) ? ($stamp['deployed_at'] ?? null) : null;
    }

    return response()->json($payload);
});

// TEST-only immediate deploy trigger (GitHub Actions → cPanel). Disabled when
// TEST_DEPLOY_WEBHOOK_SECRET is unset; always 404 on non-test hosts.
Route::post('/deploy/test-pull', TestDeployWebhookController::class)
    ->middleware('throttle:10,1');

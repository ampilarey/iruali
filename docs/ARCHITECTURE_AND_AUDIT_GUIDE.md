# Iruali — Architecture & Audit Implementation Guide

> **Status:** Reference document. Do not implement without reviewing each section.
> **Codebase:** Laravel 12 / PHP 8.2+ / Blade + Tailwind / Sanctum API
> **Context:** Maldives marketplace platform (`APP_TIMEZONE=Asia/Male`)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Architecture Decision Rationale](#2-architecture-decision-rationale)
3. [Phase 1 — Full Audit Findings](#3-phase-1--full-audit-findings)
4. [Phase 2 — Must-Fix Issues (Priority Order)](#4-phase-2--must-fix-issues-priority-order)
5. [Phase 3 — Modular Monolith Refactor Plan](#5-phase-3--modular-monolith-refactor-plan)
6. [Phase 4 — Mobile Readiness](#6-phase-4--mobile-readiness)
7. [Phase 5 — Full Security Review](#7-phase-5--full-security-review)
8. [Phase 6 — Website Quality Improvements](#8-phase-6--website-quality-improvements)
9. [Phase 7 — Test Coverage Plan](#9-phase-7--test-coverage-plan)
10. [Phase 8 — Production Checklist](#10-phase-8--production-checklist)
11. [Manual Business Decisions Needed](#11-manual-business-decisions-needed)

---

## 1. Executive Summary

### Current State

Iruali is a multi-vendor marketplace built on Laravel 12. It has a functioning web storefront, seller area, admin dashboard, Sanctum-based JSON API, and several supporting systems (loyalty points, vouchers, referrals, OTP 2FA, multilingual support via `spatie/laravel-translatable`).

The codebase is reasonably structured for an early-stage project. Controllers are separated into `Admin/`, `Customer/`, `Api/`, and `Seller/` namespaces. Services exist for cart, orders, discounts, SEO, notifications, and localization. Three policies cover orders, products, and vouchers. There are existing Feature and Unit tests covering key flows.

### What Is Working Well

- Controller namespace separation is already meaningful
- Sanctum API layer exists and is versioned (`/api/v1/`)
- OTP / 2FA integration via `pragmarx/google2fa`
- Spatie packages (permissions, translatable, backup) are production-grade
- Soft deletes on products, orders, users
- Island-based delivery model (domain-appropriate)
- SEO service and sitemap command exist
- Backup command with OneDrive support exists

### Critical Problems Found

| Severity | Issue |
|----------|-------|
| **Critical** | Admin routes protected only by `auth`, not `role:admin` — any logged-in user can access admin |
| **Critical** | Public order tracking uses raw numeric `{order}` ID — enumerable IDOR |
| **Critical** | API route order conflict: `/products/featured` declared after `/products/{product}` — Laravel resolves `featured` as a product ID |
| **High** | `.env.example` has an unresolved git merge conflict — not a valid file |
| **High** | No stock locking in checkout — race conditions possible under concurrent load |
| **High** | Loyalty points economics are not defined in config — scattered assumptions |
| **High** | Product creation endpoint (`POST /api/v1/products`) is inside `auth:sanctum` but not role-gated — any authenticated user can create products via API |
| **Medium** | Seller routes have `role:seller` middleware but only 1 controller (`SellerController`) — missing seller product management web routes |
| **Medium** | Route name duplication: `admin.admin.sellers.approve` (double prefix) |
| **Medium** | No `role:admin` middleware — admin protection is effectively absent |
| **Low** | Test routes (`/test-css`, `/test-images`) are live in production routing |
| **Low** | `IslandController` at root controller level (not namespaced) |

### Architecture Verdict

**One Laravel modular monolith is the correct choice.** The codebase is not large enough or operationally complex enough to justify splitting into separate apps. The existing separation between `Admin/`, `Customer/`, `Api/`, and `Seller/` controllers is a solid starting point for internal domain organization. The target is to deepen this into a proper modular monolith under `app/Domains/` while keeping all routing, views, and models stable.

**React is not justified.** The website is a standard e-commerce storefront. Blade + Tailwind + Alpine is appropriate for all current pages. React should only be considered later if a drag-and-drop product builder, live analytics dashboard, or similarly interactive seller tool is built.

---

## 2. Architecture Decision Rationale

### Why Not Split Into Multiple Apps

- No operational justification exists at this stage
- Shared database, shared auth model, shared domain events — splitting would require a distributed system with all its overhead
- Deployment complexity would increase without proportional benefit
- A single well-structured monolith is easier to test, deploy, and reason about

### Why Not a Full React SPA

- The storefront is a catalog + checkout flow — server rendering is faster, more SEO-friendly, and simpler
- Blade + Tailwind achieves excellent UI quality without React
- No current page requires the level of interactivity that justifies React
- A future mobile app does **not** require a React web frontend

### Target Architecture

```
One Laravel 12 app
  ├── Blade + Vite + Tailwind website (server-rendered)
  ├── Alpine.js for light interactivity (cart updates, modal toggles)
  ├── Internal domain modules under app/Domains/
  ├── Eloquent models remain in app/Models/
  └── Sanctum API layer for future mobile app
```

### Future Mobile Path

A React Native or Flutter app can be added later by consuming the existing `/api/v1/` endpoints. The backend needs to be hardened and standardized, but no second app needs to be created now.

---

## 3. Phase 1 — Full Audit Findings

### 3.1 Route Structure

**File:** `routes/web.php`

| Area | Middleware | Problem |
|------|-----------|---------|
| Admin | `auth` only | Missing `role:admin` — critical security gap |
| Seller | `auth`, `role:seller` | Correct, but only GET routes for dashboard/products/orders/profile/analytics |
| Public tracking | None | Uses `{order}` numeric ID — IDOR risk |
| Products (web) | None | No static routes like `/products/featured` — not an issue on web, but see API |
| Test routes | None | `/test-css` and `/test-images` exposed publicly |

**File:** `routes/api.php`

| Problem | Location |
|---------|----------|
| `/products/featured` declared after `/products/{product}` | Lines 36–38 |
| `/products/on-sale` declared after `/products/{product}` | Lines 36–38 |
| `POST /products` is inside `auth:sanctum` but not role-gated | Line 82 |
| No rate limiting on API auth endpoints | Lines 29–32 |
| No admin-specific API routes | Entire file |
| No seller-specific API routes | Entire file |

**Route name duplication in web.php:**
```php
// Line 118 — generates name: admin.admin.sellers.approve (double admin prefix)
Route::post('/sellers/{seller}/approve', ...)->name('admin.sellers.approve');
```
This should be `->name('sellers.approve')` inside the `name('admin.')` group.

### 3.2 Middleware Coverage

**Existing middleware:**
- `SetLocale` — wraps all web routes (correct)
- `CheckRole` — used as `role:seller` (works)
- `auth` — Laravel built-in
- `guest` — Laravel built-in
- `throttle` — applied to login/register/OTP (good)

**Missing:**
- No `role:admin` middleware usage on admin routes
- No custom middleware for seller-approved check (sellers need `seller_approved = true`)
- No API rate limiting middleware beyond Sanctum defaults

### 3.3 Controllers and Business Logic

| Controller | Business Logic Inside? | Should Move To |
|-----------|----------------------|----------------|
| `Customer/CheckoutController` | Yes — stock check, pricing, loyalty | `OrderService`, `CartService` |
| `Customer/CartController` | Partially | `CartService` (already exists) |
| `Customer/AuthController` | Yes — OTP, 2FA, session | Auth domain service |
| `Admin/AdminController` | Yes — approval logic | Admin domain service |
| `Api/AuthController` | Yes — token generation | Same Auth domain service |

### 3.4 Model Relationships

From migrations, the following ownership fields exist:

| Model | Owner Field | Notes |
|-------|-------------|-------|
| `Product` | `seller_id` (from migration) | Must verify this is `belongsTo(User)` via `seller_id` |
| `Order` | `user_id` | Customer ownership |
| `Cart` | `user_id` | Customer ownership |
| `Wishlist` | `user_id` | Customer ownership |
| `SupportTicket` | `user_id` | Customer ownership |
| `ProductReview` | `user_id` | Customer ownership |

**Key risk:** If `ProductPolicy` or seller-scoped queries use a different field than `seller_id`, there is an ownership bypass. This must be verified.

### 3.5 Existing Policies

| Policy | Covers |
|--------|--------|
| `ProductPolicy` | Product ownership (seller check) |
| `OrderPolicy` | Order ownership (customer check) |
| `VoucherPolicy` | Voucher management (admin check) |

**Missing policies:**
- No `CartPolicy` (ownership enforced in controller, not policy)
- No `WishlistPolicy`
- No `SupportTicketPolicy`
- No `UserPolicy` (admin user management)

### 3.6 Services

| Service | Purpose | Quality Notes |
|---------|---------|---------------|
| `CartService` | Cart operations | Exists — verify it handles voucher + loyalty correctly |
| `OrderService` | Order placement | Exists — verify stock locking |
| `DiscountService` | Price calculation | Exists — must be server-authoritative |
| `SeoService` | Meta tags | Exists and good |
| `NotificationService` | User notifications | Exists |
| `LocalizationService` | i18n support | Exists |
| `ApiService` | External API calls | Purpose unclear — review |

**Missing services:**
- No `LoyaltyService` — loyalty points logic is inline
- No `StockService` — stock management inline
- No `TrackingService` — tracking token logic doesn't exist yet

### 3.7 Validation Coverage

**Form Requests present:**
- `RegisterUserRequest`
- `StoreCategoryRequest`
- `StoreOrderRequest`
- `StoreProductRequest`
- `StoreVoucherRequest`
- `UpdateProductRequest`
- `UpdateVoucherRequest`
- `TranslatableRequest`

**Missing Form Requests:**
- `LoginRequest` (throttling done in routes, not in request)
- `CheckoutRequest` (checkout validation likely inline)
- `UpdateProfileRequest`
- `ChangePasswordRequest`
- `ApplyVoucherRequest`
- `StoreAddressRequest` (if addresses are stored)
- `StoreSupportTicketRequest`

### 3.8 OTP / 2FA

Using `pragmarx/google2fa` — a well-regarded library. OTP model exists in `app/Models/OTP.php`.

**Must verify:**
- OTPs expire after a defined time (check `otps` migration for `expires_at`)
- OTPs are invalidated after use (one-time flag)
- Resend is throttled (routes show `throttle:3,1` — good)
- 2FA session not bypassable by hitting protected routes directly (middleware chain)

### 3.9 Cart Logic

`CartService` exists. Must verify:
- Voucher application stores discounted amount server-side, not client-sent
- Loyalty point redemption is validated against user's actual balance
- Cart items reference current product prices at checkout time, not at add-to-cart time
- Cart is cleared after successful order

### 3.10 Order Tracking

**Current implementation in `routes/web.php`:**
```php
Route::get('track/order/{order}', [OrderTrackingController::class, 'show'])
    ->name('order.track.show');
```

`{order}` is a numeric ID — any user can enumerate orders by incrementing the ID. This must be replaced with a `tracking_token` (a random UUID or 32-character hex string).

### 3.11 Pricing Source of Truth

**Risk:** If the checkout controller accepts a price from the client (hidden form field or JS), pricing is manipulated. Must confirm `DiscountService` computes totals server-side from database prices, and that the checkout `process()` action uses `DiscountService`, not client-submitted totals.

### 3.12 Stock Handling

**Current assumption:** Stock is checked and decremented in `OrderService` or `CheckoutController`. Without `lockForUpdate()` inside a DB transaction, two simultaneous checkouts for the same last item will both succeed.

### 3.13 Database

**Migrations look complete.** Notable fields:
- `voucher_id` on orders (from `add_voucher_to_orders_table`)
- `loyalty_points_earned`, `loyalty_points_used` on orders
- `referral_code` on users (from `add_referral_to_users_table`)
- `flash_sale_price`, `flash_sale_ends_at` on products
- `seller_approved` on users
- `phone_verified_at` on users
- `login_count` on users

**Missing indexes to add:**
- `products.seller_id`
- `products.slug`
- `products.status`
- `orders.tracking_token` (after adding the column)
- `vouchers.code` (unique)
- `orders.order_number` (unique)
- `users.referral_code` (unique)

### 3.14 Frontend Structure

Views are in organized folders. Key observations:

- `resources/views/layouts/app.blade.php` — single layout for all areas (admin, seller, customer) — should be split into at least 3 layouts
- `resources/views/components/` — 6 components exist — good pattern, needs expansion
- `resources/views/admin/` — 5 admin sections — check if they share the public layout or a dedicated admin layout
- No dedicated seller layout — seller dashboard uses same layout as storefront

### 3.15 API Resources

7 API Resources exist:
`AuthUserResource`, `CartResource`, `CategoryResource`, `OrderResource`, `ProductResource`, `UserResource`, `WishlistItemResource`

**Good foundation.** Must ensure all API controllers use these resources consistently, not raw `$model->toArray()` or `response()->json($model)`.

---

## 4. Phase 2 — Must-Fix Issues (Priority Order)

These should be implemented first, before any refactoring.

---

### Fix 1: Admin Route Authorization (Critical)

**Problem:** Admin routes only have `auth` middleware. Any authenticated user (customer, seller) can access the admin dashboard.

**Files to change:**
- `routes/web.php` — add `role:admin` to admin route group
- `app/Http/Middleware/CheckRole.php` — verify it handles `admin` role
- All `Admin/` controllers — add `$this->authorize()` or `Gate::authorize()` calls as defense in depth

**Implementation:**
```php
// routes/web.php
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // ... existing admin routes
});
```

**Fix route name duplication on same file:**
```php
// Change from:
Route::post('/sellers/{seller}/approve', ...)->name('admin.sellers.approve');
Route::post('/products/{product}/approve', ...)->name('admin.products.approve');
// To:
Route::post('/sellers/{seller}/approve', ...)->name('sellers.approve');
Route::post('/products/{product}/approve', ...)->name('products.approve');
```

**Tests to add:**
- `tests/Feature/AdminAuthorizationTest.php`
  - A customer (no admin role) gets 403 on `/admin/dashboard`
  - A seller gets 403 on `/admin/dashboard`
  - An admin user gets 200 on `/admin/dashboard`

---

### Fix 2: API Route Order Conflict (Critical)

**Problem:** In `routes/api.php`, `/products/featured` and `/products/on-sale` are declared after `/products/{product}`. Laravel registers routes in declaration order, so `featured` and `on-sale` are treated as `{product}` values.

**File to change:** `routes/api.php`

**Implementation:**
```php
// Move static routes BEFORE the parameterized route:
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/on-sale', [ProductController::class, 'onSale']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
```

**Also lock down `POST /products`:**
```php
// This should NOT be in public or generic auth group:
Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
});
```

**Tests to add:**
- `tests/Feature/ApiRouteConflictTest.php`
  - `GET /api/v1/products/featured` returns featured products, not a 404/model error
  - `GET /api/v1/products/on-sale` returns sale products, not a 404/model error

---

### Fix 3: Public Order Tracking IDOR (Critical)

**Problem:** `/track/order/{order}` uses numeric order ID. Anyone can enumerate `track/order/1`, `track/order/2`, etc. and see other users' order status.

**Migration needed:** Add `tracking_token` column to `orders` table.

**Files to change:**
- New migration: `add_tracking_token_to_orders_table.php`
- `app/Models/Order.php` — auto-generate token in `creating` event
- `app/Http/Controllers/Customer/OrderTrackingController.php` — bind by token
- `routes/web.php` — change route parameter
- Any view that generates a tracking link

**Migration:**
```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('tracking_token', 64)->unique()->nullable()->after('id');
});
```

**Model boot:**
```php
protected static function booted(): void
{
    static::creating(function (Order $order) {
        $order->tracking_token = bin2hex(random_bytes(32));
    });
}
```

**Route change:**
```php
// Change from:
Route::get('track/order/{order}', [OrderTrackingController::class, 'show']);
// To:
Route::get('track/order/{token}', [OrderTrackingController::class, 'show'])
    ->where('token', '[a-f0-9]{64}');
```

**Tests to add:**
- `tests/Feature/OrderTrackingTest.php`
  - Valid token returns order status page
  - Invalid token returns 404
  - Numeric ID in URL returns 404 (old behavior blocked)
  - Token does not expose other users' orders

---

### Fix 4: `.env.example` Merge Conflict (High)

**Problem:** `.env.example` contains `<<<<<<< HEAD`, `=======`, `>>>>>>>` markers — unresolved git conflict. This makes it unusable and confusing.

**File to change:** `.env.example`

**Resolution:** Merge both versions into one clean template that covers all needed variables with sensible placeholder values. Key variables to include:

```env
APP_NAME="Iruali"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Male
APP_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iruali
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@yourdomain.com

SANCTUM_STATEFUL_DOMAINS=yourdomain.com

VITE_APP_URL="${APP_URL}"
```

**Also check `.gitignore`:** Ensure `.env` is listed. Ensure no `*.zip`, `*.tar.gz`, or deployment archives are tracked.

---

### Fix 5: Checkout Stock Concurrency (High)

**Problem:** Stock checks and decrements in checkout are likely not wrapped in a database transaction with row locking.

**File to change:** `app/Services/OrderService.php` (or `CheckoutController` if logic is inline)

**Implementation pattern:**
```php
DB::transaction(function () use ($cartItems) {
    foreach ($cartItems as $item) {
        $product = Product::lockForUpdate()->find($item->product_id);
        
        if ($product->stock < $item->quantity) {
            throw new InsufficientStockException($product->name);
        }
        
        $product->decrement('stock', $item->quantity);
    }
    
    // Create order here, inside same transaction
});
```

**Tests to add:**
- `tests/Feature/CheckoutStockTest.php`
  - Checkout with sufficient stock succeeds
  - Checkout with 0 stock throws exception and order is not created
  - Concurrent checkout for last item only succeeds once (simulate with two requests)

---

### Fix 6: Loyalty Points Economics in Config (High)

**Problem:** Loyalty point earn/redeem rates are not in `config/`. They are scattered in service/controller code, making them impossible to change without touching business logic.

**File to create:** `config/loyalty.php`

```php
return [
    'earn_rate'          => env('LOYALTY_EARN_RATE', 1),        // points per MVR spent
    'redeem_rate'        => env('LOYALTY_REDEEM_RATE', 0.1),    // MVR value per point
    'min_redeem'         => env('LOYALTY_MIN_REDEEM', 100),     // minimum points to redeem
    'max_redeem_percent' => env('LOYALTY_MAX_REDEEM_PCT', 20),  // max % of order total redeemable
    'expiry_days'        => env('LOYALTY_EXPIRY_DAYS', 365),    // points expiry
];
```

**All earn/redeem calculations should reference `config('loyalty.*')` values.**

**Tests to add:**
- `tests/Unit/LoyaltyCalculationTest.php`
  - Earn rate applies correctly to order total
  - Redeem rate applies correctly to point balance
  - Redemption capped at `max_redeem_percent` of order total
  - Redemption blocked below `min_redeem` threshold

---

### Fix 7: Vite Asset Base Configuration (Medium)

**Problem:** Hard-coded deployment-specific asset base assumptions may exist in `vite.config.js` or `ViteServiceProvider`.

**Files to check:**
- `vite.config.js`
- `app/Providers/ViteServiceProvider.php`
- `resources/css/app.css`

**Rule:** `ASSET_URL` or `VITE_ASSET_URL` should come from `.env`, not be hard-coded. The `vite.config.js` base should use the environment variable or remain as `/`.

---

### Fix 8: Test Routes in Production (Low)

**Problem:** `/test-css` and `/test-images` are registered routes with no environment guard.

**Fix:**
```php
// routes/web.php — wrap in environment check
if (app()->isLocal()) {
    Route::get('/test-css', fn() => view('test-css'));
    Route::get('/test-images', fn() => view('test-images'));
}
```

---

## 5. Phase 3 — Modular Monolith Refactor Plan

This is a **safe, incremental refactor**. Models stay in `app/Models/`. Business logic moves into domain modules. Controllers become thin orchestrators.

### 5.1 Target Directory Structure

```
app/
├── Domains/
│   ├── Admin/
│   │   ├── Actions/
│   │   │   ├── ApproveSeller.php
│   │   │   └── ApproveProduct.php
│   │   ├── Services/
│   │   │   └── AdminService.php
│   │   └── DTOs/
│   ├── Auth/
│   │   ├── Actions/
│   │   │   ├── LoginUser.php
│   │   │   ├── RegisterUser.php
│   │   │   ├── SendOTP.php
│   │   │   └── VerifyOTP.php
│   │   └── Services/
│   │       └── OtpService.php
│   ├── Catalog/
│   │   ├── Actions/
│   │   │   ├── CreateProduct.php
│   │   │   ├── UpdateProduct.php
│   │   │   └── DeleteProduct.php
│   │   ├── Services/
│   │   │   └── ProductQueryService.php
│   │   └── DTOs/
│   │       └── ProductData.php
│   ├── Cart/
│   │   ├── Actions/
│   │   │   ├── AddToCart.php
│   │   │   ├── UpdateCartItem.php
│   │   │   └── ApplyVoucher.php
│   │   └── Services/
│   │       └── CartService.php          ← move existing CartService here
│   ├── Checkout/
│   │   ├── Actions/
│   │   │   ├── ProcessCheckout.php
│   │   │   └── RedeemLoyaltyPoints.php
│   │   └── Services/
│   │       └── CheckoutService.php
│   ├── Orders/
│   │   ├── Actions/
│   │   │   ├── PlaceOrder.php
│   │   │   ├── CancelOrder.php
│   │   │   └── GenerateTrackingToken.php
│   │   └── Services/
│   │       └── OrderService.php         ← move existing OrderService here
│   ├── Loyalty/
│   │   ├── Actions/
│   │   │   ├── EarnPoints.php
│   │   │   └── RedeemPoints.php
│   │   └── Services/
│   │       └── LoyaltyService.php       ← extract from inline code
│   ├── Vouchers/
│   │   ├── Actions/
│   │   │   └── ValidateVoucher.php
│   │   └── Services/
│   │       └── VoucherService.php       ← extract from DiscountService
│   ├── Sellers/
│   │   ├── Actions/
│   │   │   └── GetSellerProducts.php
│   │   └── Services/
│   │       └── SellerService.php
│   ├── Customers/
│   │   └── Services/
│   │       └── CustomerProfileService.php
│   ├── Support/
│   │   ├── Actions/
│   │   │   └── CreateSupportTicket.php
│   │   └── Services/
│   │       └── SupportService.php
│   └── Shared/
│       ├── Traits/
│       │   └── SecureFileUpload.php     ← move existing Trait here
│       ├── Exceptions/
│       │   ├── InsufficientStockException.php
│       │   └── VoucherInvalidException.php
│       └── Helpers/
│           └── PriceHelper.php
├── Http/
│   └── Controllers/
│       ├── Admin/       ← thin, delegate to Domains/Admin/
│       ├── Api/         ← thin, delegate to domain services
│       ├── Customer/    ← thin, delegate to domain services
│       └── Seller/      ← thin, delegate to Domains/Sellers/
├── Models/              ← stay here, no change
└── Policies/            ← stay here, expand to cover all resources
```

### 5.2 Controller Responsibilities (After Refactor)

Controllers should:
1. Validate input (via Form Request)
2. Authorize the action (via Policy/Gate)
3. Call one domain Action or Service method
4. Return a view or JSON response

Controllers should NOT:
- Perform DB queries directly
- Contain business rules (pricing, stock logic, points math)
- Have long methods (>20 lines is a warning sign)

### 5.3 Route File Organization

Split routes into logical files while keeping URLs identical:

```
routes/
├── web.php              ← entry point, includes below files
├── web/
│   ├── public.php       ← /, /shop, /products, /categories, /search
│   ├── auth.php         ← login, register, 2fa, verification, logout
│   ├── customer.php     ← cart, wishlist, checkout, orders, account
│   ├── seller.php       ← /seller/* routes
│   └── admin.php        ← /admin/* routes
├── api.php              ← entry point, includes below files
└── api/
    ├── public.php       ← auth, products, categories, search
    ├── customer.php     ← cart, wishlist, orders, profile (sanctum)
    ├── seller.php       ← seller product/order API (sanctum + role:seller)
    └── admin.php        ← admin API (sanctum + role:admin)
```

**In `routes/web.php`:**
```php
require __DIR__.'/web/public.php';
require __DIR__.'/web/auth.php';
require __DIR__.'/web/customer.php';
require __DIR__.'/web/seller.php';
require __DIR__.'/web/admin.php';
```

All URLs remain identical. Only the file organisation changes.

### 5.4 Migration Priority for Services

| Priority | Service/Logic | Target Location |
|----------|--------------|-----------------|
| 1 | Stock check + decrement | `Domains/Checkout/Actions/ProcessCheckout.php` |
| 2 | Loyalty earn/redeem | `Domains/Loyalty/Services/LoyaltyService.php` |
| 3 | Voucher validation | `Domains/Vouchers/Services/VoucherService.php` |
| 4 | Order placement | `Domains/Orders/Actions/PlaceOrder.php` |
| 5 | CartService | `Domains/Cart/Services/CartService.php` |
| 6 | Admin approvals | `Domains/Admin/Actions/` |
| 7 | OTP/2FA logic | `Domains/Auth/Services/OtpService.php` |

### 5.5 Policies to Add

| Resource | Policy | Key Check |
|----------|--------|-----------|
| `Cart` | `CartPolicy` | `user_id === auth()->id()` |
| `Wishlist` | `WishlistPolicy` | `user_id === auth()->id()` |
| `SupportTicket` | `SupportTicketPolicy` | `user_id === auth()->id()` |
| `User` (admin manages) | `UserPolicy` | `admin role` |

### 5.6 Blade Layout Refactor

**Current:** One layout `resources/views/layouts/app.blade.php` serving all areas.

**Target:**
```
resources/views/layouts/
├── app.blade.php          ← customer storefront
├── admin.blade.php        ← admin dashboard (sidebar, admin nav)
└── seller.blade.php       ← seller dashboard (seller nav)
```

**New components to extract:**
```
resources/views/components/
├── product-card.blade.php      ← exists
├── category-card.blade.php     ← exists
├── banner.blade.php            ← exists
├── notification.blade.php      ← exists
├── seo-meta.blade.php          ← exists
├── policy-buttons.blade.php    ← exists
├── pagination.blade.php        ← new
├── breadcrumb.blade.php        ← new
├── price-display.blade.php     ← new (handles flash sale / discount display)
├── stock-badge.blade.php       ← new
├── order-status-badge.blade.php ← new
└── forms/
    ├── input.blade.php         ← new
    └── select.blade.php        ← new
```

---

## 6. Phase 4 — Mobile Readiness

The backend already has a `/api/v1/` layer with Sanctum. The goal is to make it production-quality for a future mobile app without building the mobile app now.

### 6.1 Current API Coverage

| Area | Status | Notes |
|------|--------|-------|
| Auth (login/register) | Exists | Add OTP/2FA to API flow |
| Products | Exists | Fix route order conflict first |
| Categories | Exists | Good |
| Cart | Exists | Verify voucher + loyalty via API |
| Wishlist | Exists | Good |
| Orders | Exists | Replace numeric ID in track endpoint |
| User profile | Exists | Add address endpoints |
| Search | Exists | Good |
| Seller endpoints | Missing | Add when seller mobile is needed |
| Admin endpoints | Missing | Add when admin mobile is needed |
| OTP / 2FA | Missing | Needed for mobile login flow |
| Push notifications | Missing | Future — Firebase/APNs |
| Image upload | Partial | Needs mobile-safe file handling |

### 6.2 Required Before Mobile Launch

1. **OTP API endpoints** — Mobile needs to trigger and verify OTPs via API
2. **Address management API** — `GET/POST/PUT/DELETE /api/v1/user/addresses`
3. **Order tracking by token** — Fix the IDOR issue first, then expose token in order response
4. **Standardized error responses** — All API errors should return consistent JSON shape
5. **API Resources on all endpoints** — No raw model output
6. **Sanctum token management** — Ensure tokens can be revoked per-device (Sanctum supports this)

### 6.3 Sanctum Configuration for Mobile

Sanctum is appropriate for mobile. Key configuration:

**`config/sanctum.php`** (publish if not already):
```php
'expiration' => 60 * 24 * 365, // 1 year for mobile tokens (or null for no expiry)
'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
```

Mobile apps should use token-based auth (`/api/v1/login` → returns token → stored securely on device). The stateful cookie flow is for the web app only.

### 6.4 Standardized API Response Shape

All API responses should follow a consistent structure. Introduce a response helper or extend `BaseController`:

```php
// Success
{
    "success": true,
    "data": { ... },
    "message": "Optional message"
}

// Error
{
    "success": false,
    "message": "Human-readable error",
    "errors": { "field": ["validation message"] }  // only on validation errors
}

// Paginated
{
    "success": true,
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 73
    }
}
```

### 6.5 Rate Limiting for API

Add named rate limiters in `AppServiceProvider` or `RouteServiceProvider`:

```php
RateLimiter::for('api-auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('api-general', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

Apply in `routes/api.php`:
```php
Route::post('/login', ...)->middleware('throttle:api-auth');
Route::post('/register', ...)->middleware('throttle:api-auth');
```

---

## 7. Phase 5 — Full Security Review

### 7.1 Authorization Matrix

| User Type | Can Access | Cannot Access |
|-----------|-----------|--------------|
| Guest | Public products, categories, search, order tracking by token | Everything else |
| Customer | Own cart, wishlist, orders, account, checkout | Other users' data, seller area, admin area |
| Seller (approved) | Seller dashboard, own products, own seller orders | Other sellers' products, admin area, other customers' data |
| Admin | All admin routes, all data | Nothing restricted |

### 7.2 IDOR Risks to Patch

These are the confirmed and likely IDOR risks based on route analysis:

| Route | Risk | Fix |
|-------|------|-----|
| `GET /orders/{order}` (web) | Customer can view any order by ID | `OrderPolicy::view` — check `user_id` |
| `POST /orders/{order}/cancel` | Customer can cancel any order by ID | `OrderPolicy::cancel` |
| `GET /track/order/{order}` | Enumerable order tracking | Replace with `tracking_token` |
| `PUT /cart/update/{item}` | Customer can update any cart item | Check `CartItem` belongs to user's cart |
| `DELETE /cart/remove/{item}` | Customer can remove any cart item | Same check |
| `DELETE /wishlist/remove/{product}` | Currently by product ID — needs to check user's wishlist |

### 7.3 Mass Assignment Audit

All models must have either `$fillable` (allowlist) or `$guarded = ['id']`. Check:

- `User` — must NOT have `role`, `seller_approved`, or `loyalty_points` in `$fillable`
- `Product` — `seller_id` should NOT be in `$fillable` if a customer could submit it
- `Order` — `status`, `loyalty_points_earned` should not be fillable from user input

### 7.4 File Upload Security

`SecureFileUpload` trait exists. Must verify it:
- Validates MIME type via `finfo` (not just file extension)
- Validates file size (e.g., max 5MB for product images)
- Stores files in `storage/app/public/` (not in `public/` directly)
- Uses a hashed, unpredictable filename (not user-supplied name)
- Does not allow `php`, `html`, `js` extensions

### 7.5 OTP Security Checklist

- [ ] OTP expires after N minutes (check `expires_at` column in `otps` migration)
- [ ] OTP is single-use (marked `used_at` or deleted after verification)
- [ ] Resend is throttled (`throttle:3,1` exists in routes — good)
- [ ] Verify endpoint is throttled (`throttle:5,1` exists — good)
- [ ] OTP cannot be used for a different phone/email than it was sent to
- [ ] TOTP (2FA) secret is stored encrypted or at minimum not in a plain-text accessible field

---

## 8. Phase 6 — Website Quality Improvements

### 8.1 Homepage

Audit `HomeController@index` and `resources/views/home.blade.php` for:
- Are banners loaded efficiently (single eager-loaded query)?
- Are featured products limited and cached?
- Is SEO meta set via `SeoService`?
- Does the page render well on mobile (Tailwind responsive classes)?

### 8.2 Product Pages

- `resources/views/products/show.blade.php` — verify image gallery, variants, stock badge, review display
- Implement `<x-price-display>` component for consistent flash sale / regular price display
- Add structured data (JSON-LD `Product` schema) via `SeoService` for SEO

### 8.3 Cart and Checkout UX

- Cart updates should use Alpine.js or a minimal fetch call for quantity changes without full page reload
- Checkout must show loading state on form submit (prevent double-submit)
- Voucher application should give clear feedback (success/error inline)
- Loyalty points toggle should show running total impact

### 8.4 Seller Dashboard

- Create a dedicated `resources/views/layouts/seller.blade.php`
- Seller products view should include status filters (pending approval, active, out of stock)
- Seller orders should show item-level detail and fulfillment status

### 8.5 Admin Dashboard

- Create a dedicated `resources/views/layouts/admin.blade.php`
- Seller approval queue is a priority workflow — make it prominent
- Product moderation queue should show seller name, product image, category
- Add a basic stats bar (total orders, revenue, pending approvals) to dashboard

### 8.6 Search and Filtering

- `SearchController` should support filtering by: category, price range, island, seller
- Search results view should have a sidebar filter component
- Empty state (no results) should suggest related categories

### 8.7 Multilingual Quality

Using `spatie/laravel-translatable`. Verify:
- Language switcher updates `app()->setLocale()` via `LocaleController`
- All user-facing strings are in `resources/lang/` files
- Product names/descriptions are stored as JSON via `Translatable` trait
- Admin inputs for translatable fields have separate inputs per locale

### 8.8 SEO Basics

- `<x-seo-meta>` component exists — verify it sets title, description, og:image
- Product URLs use slugs (`/products/{slug}` not `/products/{id}`)
- Sitemap command (`GenerateSitemap`) is scheduled in `routes/console.php`
- Canonical URL set on paginated pages
- `robots.txt` and `sitemap.xml` accessible at web root

### 8.9 Performance Opportunities

- Eager load relationships in listing queries (N+1 risk in product cards)
- Cache category tree (rarely changes)
- Cache site settings from `Setting` model
- Optimize product images via `intervention/image` on upload
- Consider adding `defer` or `async` to non-critical JS
- Add `Cache-Control` headers for public static pages

---

## 9. Phase 7 — Test Coverage Plan

### 9.1 Tests to Add

| Test File | What It Covers |
|-----------|---------------|
| `Feature/AdminAuthorizationTest.php` | Guest, customer, seller all get 403 on admin routes; admin gets 200 |
| `Feature/ApiRouteConflictTest.php` | `/api/v1/products/featured` and `/on-sale` resolve correctly |
| `Feature/OrderTrackingTest.php` | Token-based tracking works; numeric IDOR blocked |
| `Feature/CheckoutStockTest.php` | Stock race condition protection; insufficient stock rejection |
| `Feature/SellerOwnershipTest.php` | Seller cannot update/delete another seller's product |
| `Feature/CustomerOwnershipTest.php` | Customer cannot view/cancel another customer's order |
| `Feature/VoucherApplicationTest.php` | Valid voucher applies; expired voucher rejected; invalid code rejected |
| `Unit/LoyaltyCalculationTest.php` | Earn/redeem rates from config; caps and thresholds enforced |
| `Feature/CartItemOwnershipTest.php` | Customer cannot update/remove another user's cart item |
| `Feature/ApiAuthBoundaryTest.php` | Guest denied on protected API; auth passes |

### 9.2 Existing Test Coverage

These tests already exist and should pass after the fixes:

| Test File | Coverage |
|-----------|----------|
| `Feature/ApiResourceTest.php` | API resource shape |
| `Feature/ApiTest.php` | General API endpoints |
| `Feature/OrderStockCheckTest.php` | Stock check — verify it tests the lock |
| `Feature/ReferralTest.php` | Referral code flows |
| `Unit/CartServiceTest.php` | Cart service logic |
| `Unit/DiscountServiceTest.php` | Discount calculations |
| `Feature/WishlistDuplicateTest.php` | Duplicate wishlist entries |

### 9.3 Test Environment

Ensure tests use an in-memory SQLite or a dedicated test database. Key `phpunit.xml` settings:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

---

## 10. Phase 8 — Production Checklist

### 10.1 Environment Configuration

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` is set and unique
- [ ] `APP_URL` matches the actual domain with HTTPS
- [ ] `.env` is in `.gitignore` and never committed
- [ ] `.env.example` is clean and conflict-free (see Fix 4)
- [ ] All secrets rotated if ever exposed in git history

### 10.2 Application Optimization

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
composer install --optimize-autoloader --no-dev
```

### 10.3 Queue Workers and Scheduler

- [ ] Queue worker running and managed by Supervisor or Laravel Octane
- [ ] Cron job set up: `* * * * * php /path/to/artisan schedule:run`
- [ ] Scheduled commands verified: `GenerateSitemap`, `BackupCommand`
- [ ] Failed job monitoring configured

### 10.4 Security Settings

- [ ] HTTPS enforced (redirect HTTP to HTTPS in web server or `AppServiceProvider`)
- [ ] `SESSION_SECURE_COOKIE=true` in production `.env`
- [ ] `SESSION_SAME_SITE=strict` or `lax`
- [ ] CORS configured correctly in `config/cors.php` (especially for API)
- [ ] `SANCTUM_STATEFUL_DOMAINS` includes only the web frontend domain
- [ ] Rate limiting active on auth and OTP endpoints

### 10.5 Mail

- [ ] `MAIL_MAILER` set to SMTP or a transactional service (Mailgun, Postmark, SES)
- [ ] Mail `from` address is a real sending address
- [ ] SPF/DKIM/DMARC DNS records configured for the sending domain

### 10.6 Storage and Files

- [ ] `storage/app/public` symlinked to `public/storage`
- [ ] Image uploads are stored with unpredictable paths
- [ ] Backup storage configured (OneDrive backup command exists)
- [ ] Backup encryption enabled if storing sensitive order data

### 10.7 Database

- [ ] Foreign keys and unique constraints are in migrations
- [ ] Critical indexes are present (see Phase 1.14 findings)
- [ ] Database user has minimum required permissions (not root)
- [ ] Regular automated backups verified

### 10.8 Monitoring and Logging

- [ ] `LOG_CHANNEL=stack` pointing to daily rotating files in production
- [ ] Error monitoring service connected (Sentry, Bugsnag, or Flare)
- [ ] Queue failure notifications configured
- [ ] Uptime monitoring for the public domain

### 10.9 Cache and Performance

- [ ] `CACHE_STORE=redis` recommended for production (switch from `file`)
- [ ] `SESSION_DRIVER=redis` recommended for production
- [ ] Opcache enabled in PHP configuration
- [ ] CDN or object storage for product images (optional but recommended)

---

## 11. Manual Business Decisions Needed

These cannot be implemented without explicit product/business decisions from the team:

| Topic | Question | Impact |
|-------|----------|--------|
| **Loyalty economics** | What is the earn rate (points per MVR)? Redeem rate? Min threshold? Max cap? | Config values in `config/loyalty.php` |
| **Seller visibility rules** | Can customers see which seller a product belongs to? Is the seller name shown on product pages? | Product page UI, seller brand page |
| **Seller approval flow** | Auto-approve sellers on registration? Or require admin approval? | `seller_approved` field usage |
| **Refund and cancellation policy** | When can a customer cancel an order? Who initiates refunds? Is stock restored on cancellation? | `OrderService`, status machine |
| **Product approval** | Are new products approved by admin before going live? | `admin.products.approve` route exists but flow unclear |
| **Delivery islands** | How does island selection affect shipping cost calculation? Is it manual or computed? | Checkout flow, `islands` + `product_island` tables |
| **Voucher stacking** | Can multiple vouchers be applied to one order? | `CartService::applyVoucher` |
| **Flash sales** | Who can create flash sales? Is it time-limited automatically? | `flash_sale_ends_at` migration exists but admin UI unclear |
| **OTP channel** | Is SMS actually working? What SMS provider? | `ApiService` — purpose unclear, may be SMS gateway |
| **Referral rewards** | What does the referrer receive? What does the referred user receive? | `ReferralTest.php` exists but economics not in config |
| **Order tracking exposure** | Should tracking be completely public (no auth needed)? Or require order number + email verification? | `OrderTrackingController` |
| **Mobile app timeline** | When is the mobile app planned? This affects API hardening priority | Phase 4 planning |

---

*This document should be reviewed before beginning any implementation. Start with Phase 2 fixes in priority order. Phase 3 modularization should be done incrementally without breaking Phase 2 changes.*

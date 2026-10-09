<?php

namespace Tests\Feature\Concerns;

use App\Http\Controllers\Seller\HelpController;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\SellerPayout;
use App\Models\ShopDiscountCode;
use App\Models\ShopStaff;
use App\Models\ShopStaffInvitation;
use App\Models\User;
use App\Support\ShopStaffAccess;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Shop staff in feature tests. A shop is its owner's account; its staff sign in with their own and
 * work for the shop within their role (config/shop_staff.php).
 *
 * Cover any Seller Centre page as staff, e.g. a new one after a merge:
 *
 *   $shop = $this->staffedShop();
 *   $this->asShopStaff($shop, 'manager')->get(route('seller.preorders'))->assertOk();
 *   $this->callSellerRouteAsStaff('seller.preorders', [], 'packer', $shop)->assertForbidden();
 *   $this->assertStaffAccessFollowsConfig('seller.quotes.show', ['quote' => $quote], $shop);
 *
 * callSellerRouteAsStaff() uses the route's own HTTP method and fills the parameters you leave out
 * with the shop's own records (sellerRouteFixtures(); add a case there for a new parameter name).
 */
trait ActsAsShopStaff
{
    /** An approved shop: the owner's account, with the seller role. */
    protected function staffedShop(string $name = 'Lagoon Traders', array $attributes = []): User
    {
        $shop = User::factory()->create(array_merge(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name], $attributes));
        $shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $shop;
    }

    /** A new customer account put on the shop's staff with this role. */
    protected function staffMember(User $shop, string $role = 'manager', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer'])->id);

        $member = new ShopStaff(['role' => $role]);
        $member->forceFill(['shop_id' => $shop->id, 'user_id' => $user->id, 'invited_by' => $shop->id])->save();

        return $user;
    }

    /**
     * Act as a member of the shop's staff (a new one with this role unless $staff is given).
     */
    protected function asShopStaff(User $shop, string $role = 'manager', ?User $staff = null): static
    {
        return $this->actingAs($staff ?? $this->staffMember($shop, $role));
    }

    /**
     * Call a Seller Centre route as a staff member of $shop (a new shop when null), with the route's
     * HTTP method. Parameters left out are the shop's own records (sellerRouteFixtures()).
     */
    protected function callSellerRouteAsStaff(string $routeName, array $parameters = [], string $role = 'manager', ?User $shop = null, array $data = [], ?User $staff = null): TestResponse
    {
        $shop ??= $this->staffedShop();
        $route = app('router')->getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "There is no route named {$routeName}.");

        $missing = array_values(array_diff($route->parameterNames(), array_keys($parameters)));
        $parameters += $this->sellerRouteFixtures($shop, $missing);
        $method = collect($route->methods())->first(fn ($method) => $method !== 'HEAD');

        $this->asShopStaff($shop, $role, $staff);

        return $this->call($method, route($routeName, $parameters), $data);
    }

    /**
     * Every staff role against one Seller Centre route: a role whose list in config/shop_staff.php
     * allows it is let in (no 403), every other role gets 403 (the dashboard sends them to their
     * first page instead).
     */
    protected function assertStaffAccessFollowsConfig(string $routeName, array $parameters = [], ?User $shop = null, array $data = []): void
    {
        $shop ??= $this->staffedShop();

        foreach (ShopStaffAccess::roles() as $role) {
            $response = $this->callSellerRouteAsStaff($routeName, $parameters, $role, $shop, $data);

            if (ShopStaffAccess::allows($role, $routeName)) {
                // Let in: the page, a redirect (saved, or sent back with errors) or a 404 for a record the action does not apply to
                $this->assertContains($response->getStatusCode(), [200, 302, 404], "A {$role} should be able to open {$routeName}, got {$response->getStatusCode()}.");
            } elseif ($routeName === 'seller.dashboard') {
                $response->assertRedirect(route((string) ShopStaffAccess::homeRoute($role)));
            } else {
                $this->assertSame(403, $response->getStatusCode(), "A {$role} should get 403 on {$routeName}, got {$response->getStatusCode()}.");
            }
        }
    }

    /**
     * Records of the shop for seller route parameters, by parameter name.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    protected function sellerRouteFixtures(User $shop, array $names): array
    {
        $product = null;
        $order = null;
        $campaign = null;
        $productFor = function () use ($shop, &$product): Product {
            return $product ??= Product::factory()->create(['seller_id' => $shop->id, 'is_active' => true, 'stock_quantity' => 10, 'price' => 100]);
        };
        $orderFor = function () use ($productFor, &$order): Order {
            if (! $order) {
                $order = Order::factory()->create(['status' => 'pending']);
                $order->items()->create(['product_id' => $productFor()->id, 'quantity' => 1, 'price' => 100]); // makes the shop's part
            }

            return $order;
        };

        $values = [];
        foreach ($names as $name) {
            $values[$name] = match ($name) {
                'product' => $productFor(),
                'order' => $orderFor(),
                'part' => $orderFor()->sellerOrders()->where('seller_id', $shop->id)->firstOrFail(),
                'discount' => $this->shopDiscountCodeFor($shop),
                'campaign' => $campaign ??= Campaign::factory()->create(),
                'participation' => CampaignProduct::create(['campaign_id' => ($campaign ??= Campaign::factory()->create())->id, 'product_id' => $productFor()->id, 'seller_id' => $shop->id, 'discount_percent' => 15]),
                'review' => ProductReview::create(['product_id' => $productFor()->id, 'reviewer_name' => 'Mariyam', 'reviewer_email' => 'mariyam@example.test', 'rating' => 4, 'comment' => 'Nice and quick.', 'is_approved' => true]),
                'payout' => SellerPayout::create(['seller_id' => $shop->id, 'amount' => 250, 'status' => 'paid', 'paid_at' => now()]),
                'document' => 'certificate',
                'guide' => array_key_first(HelpController::guides()),
                'member' => ShopStaff::where('user_id', $this->staffMember($shop, 'packer')->id)->firstOrFail(),
                'invitation' => $this->pendingInvitationFor($shop),
                default => $this->fail("No test record for the route parameter {{$name}}: add one to ActsAsShopStaff::sellerRouteFixtures()."),
            };
        }

        return $values;
    }

    protected function shopDiscountCodeFor(User $shop): ShopDiscountCode
    {
        $code = new ShopDiscountCode(['code' => 'STAFF'.Str::upper(Str::random(5)), 'type' => 'percent', 'value' => 10, 'applies_to' => 'all', 'is_active' => true]);
        $code->seller_id = $shop->id;
        $code->save();

        return $code;
    }

    protected function pendingInvitationFor(User $shop, string $email = '', string $role = 'packer'): ShopStaffInvitation
    {
        $invitation = new ShopStaffInvitation;
        $invitation->forceFill([
            'shop_id' => $shop->id,
            'email' => $email !== '' ? $email : Str::lower(Str::random(8)).'@example.test',
            'role' => $role,
            'token_hash' => ShopStaffInvitation::hashToken(Str::random(48)),
            'invited_by' => $shop->id,
            'expires_at' => now()->addDays(7),
        ])->save();

        return $invitation;
    }
}

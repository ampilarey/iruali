<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\SellerBankAccount;
use App\Models\SellerDeliverySetting;
use App\Models\ShopDiscountCode;
use App\Models\ShopStaff;
use App\Models\User;
use App\Services\MessagingService;
use App\Services\OrderService;
use App\Support\CurrentShop;
use App\Support\ShopStaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ActsAsShopStaff;
use Tests\TestCase;

/**
 * Shop staff in the Seller Centre: every seller route follows the role lists in config/shop_staff.php,
 * staff work on their own shop only and never see money or documents, what they change is audited
 * with them as the actor, removal works on the next request, and they can still shop as customers.
 */
class ShopStaffAccessTest extends TestCase
{
    use ActsAsShopStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_every_seller_route_follows_the_role_lists_in_config(): void
    {
        $shop = $this->staffedShop();
        $routes = ShopStaffAccess::sellerRoutes();
        $this->assertGreaterThan(60, count($routes));

        foreach ($routes as $route) {
            $this->assertStaffAccessFollowsConfig((string) $route->getName(), [], $shop);
        }

        // The owner-only pages are closed to every role
        foreach (['seller.earnings', 'seller.settings.bank', 'seller.settings.bank.update', 'seller.payouts.invoice', 'seller.settings.tax', 'seller.settings.verification.document', 'seller.settings.holiday.update', 'seller.staff', 'seller.staff.invite'] as $name) {
            foreach (ShopStaffAccess::roles() as $role) {
                $this->assertFalse(ShopStaffAccess::allows($role, $name), "{$role} must not open {$name}");
            }
        }
    }

    public function test_the_config_names_only_routes_that_exist(): void
    {
        $names = collect(ShopStaffAccess::sellerRoutes())->map(fn ($route) => (string) $route->getName());

        // A typo in a role's list would quietly close a page: every entry must match a seller route
        foreach (config('shop_staff.access') as $role => $patterns) {
            foreach ($patterns as $pattern) {
                $this->assertTrue($names->contains(fn ($name) => \App\Support\StaffAccess::matches($pattern, $name)), "config/shop_staff.php gives {$role} {$pattern}, but no seller route matches it.");
            }
        }
        // The owner-only list also names the pages that hold money, documents and staff today
        foreach (['seller.earnings', 'seller.payouts.invoice', 'seller.settings.bank', 'seller.settings.tax', 'seller.settings.verification', 'seller.settings.holiday', 'seller.staff'] as $name) {
            $this->assertTrue(Route::has($name), "{$name} should exist");
            $this->assertTrue(ShopStaffAccess::ownerOnly($name), "{$name} should be owner-only");
        }

        // Each role has a first page, and the packer's is the orders list
        $this->assertSame('seller.dashboard', ShopStaffAccess::homeRoute('manager'));
        $this->assertSame('seller.orders', ShopStaffAccess::homeRoute('packer'));
    }

    public function test_owner_only_pages_stay_closed_even_if_a_role_is_given_everything(): void
    {
        config(['shop_staff.access.manager' => ['seller.*']]);
        $shop = $this->staffedShop();
        $manager = $this->staffMember($shop, 'manager');

        foreach (['/seller/earnings', '/seller/settings/bank', '/seller/settings/tax', '/seller/settings/verification', '/seller/settings/holiday', '/seller/staff'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
        $this->actingAs($manager)->get('/seller/analytics')->assertOk();
    }

    public function test_owners_see_their_seller_centre_as_before_plus_the_staff_page(): void
    {
        $shop = $this->staffedShop('Coral Corner');

        $page = $this->actingAs($shop)->get('/seller/dashboard')->assertOk()->assertSee('Coral Corner')->assertDontSee('data-shop-staff-role', false);
        foreach (['seller.dashboard', 'seller.products.index', 'seller.orders', 'seller.earnings', 'seller.analytics', 'seller.profile', 'seller.settings.bank', 'seller.staff'] as $route) {
            $page->assertSee('data-seller-tab="'.$route.'"', false);
        }
        $this->actingAs($shop)->get('/seller/staff')->assertOk()->assertSee('Invite someone');
        $this->assertFalse(CurrentShop::isStaff());
        $this->assertTrue(CurrentShop::get()->is($shop));
    }

    public function test_a_packer_lands_on_orders_and_sees_only_their_pages(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $packer = $this->staffMember($shop, 'packer', ['name' => 'Ibrahim Waheed']);

        $this->actingAs($packer)->get('/seller/dashboard')->assertRedirect(route('seller.orders'));
        $page = $this->actingAs($packer)->get('/seller/orders')->assertOk()
            ->assertSee('Coral Corner')
            ->assertSee('Signed in as Ibrahim Waheed · Packer');

        foreach (['seller.orders', 'seller.stock', 'seller.questions', 'seller.help'] as $route) {
            $page->assertSee('data-seller-tab="'.$route.'"', false);
        }
        foreach (['seller.dashboard', 'seller.products.index', 'seller.campaigns', 'seller.discounts', 'seller.returns', 'seller.reviews', 'seller.earnings', 'seller.analytics', 'seller.profile', 'seller.settings.bank', 'seller.settings.delivery', 'seller.staff'] as $route) {
            $page->assertDontSee('data-seller-tab="'.$route.'"', false);
        }

        // The account menu takes them to the Seller Centre
        $this->actingAs($packer)->get('/account')->assertOk()->assertSee('Seller Centre');
    }

    public function test_a_manager_runs_the_shop_but_never_sees_money_documents_or_staff(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $shop->bankAccount()->create(['bank' => 'bml', 'account_name' => 'Coral Corner Pvt Ltd', 'account_number' => '7730000123456']);
        $manager = $this->staffMember($shop, 'manager');
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 200]);
        $order = Order::factory()->create(['status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 200]);

        // Settings opens on a page they may see; no Earnings or Staff tab
        $this->actingAs($manager)->get('/seller/dashboard')->assertOk()
            ->assertSee('data-seller-tab="seller.settings.delivery"', false)
            ->assertDontSee('data-seller-tab="seller.settings.bank"', false)
            ->assertDontSee('data-seller-tab="seller.earnings"', false)
            ->assertDontSee('data-seller-tab="seller.staff"', false);
        $this->get('/seller/settings/delivery')->assertOk()
            ->assertSee(route('seller.settings.notifications'), false)
            ->assertDontSee(route('seller.settings.bank'), false)
            ->assertDontSee(route('seller.settings.tax'), false)
            ->assertDontSee(route('seller.settings.verification'), false)
            ->assertDontSee(route('seller.settings.holiday'), false);

        // Orders and the order page: no earnings or commission
        $this->get('/seller/orders')->assertOk()->assertSee($order->order_number)->assertDontSee('You earn');
        $this->get(route('seller.orders.show', $order))->assertOk()->assertSee('Your items in this order')->assertDontSee('Your earnings')->assertDontSee('Commission (');

        // Profile: the shop's details, never the owner's name, phone or bank account
        $this->get('/seller/profile')->assertOk()
            ->assertSee('name="business_name"', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('Payout bank account')
            ->assertDontSee('Coral Corner Pvt Ltd')
            ->assertDontSee(SellerBankAccount::mask('7730000123456'));
        $this->put('/seller/profile', ['business_name' => 'Coral Corner Maldives', 'name' => 'Someone Else', 'phone' => '7999999', 'payout_account_number' => '123456'])->assertRedirect(route('seller.profile'));
        $owner = $shop->fresh();
        $this->assertSame('Coral Corner Maldives', $owner->business_name);
        $this->assertSame($shop->name, $owner->name);
        $this->assertSame($shop->phone, $owner->phone);
        $this->assertNull($owner->payout_account_number);

        // Notification settings: the shop's emails, but the payout email stays the owner's
        $owner->forceFill(['notification_preferences' => ['payout' => true, 'customer' => ['marketing' => 'sms']]])->save();
        $this->get('/seller/settings/notifications')->assertOk()->assertSee('New order')->assertDontSee('Payout sent');
        $this->put('/seller/settings/notifications', ['new_order' => 0, 'return' => 1, 'low_stock' => 1, 'payout' => 0])->assertRedirect();
        $owner->refresh();
        $this->assertFalse($owner->wantsNotification('new_order'));
        $this->assertTrue($owner->wantsNotification('payout'));
        $this->assertSame(['marketing' => 'sms'], $owner->notification_preferences['customer']);

        // Money, documents, tax registration, holiday mode and staff are refused outright
        foreach (['/seller/earnings', '/seller/settings/bank', '/seller/settings/tax', '/seller/settings/verification', '/seller/settings/verification/documents/certificate', '/seller/settings/holiday', '/seller/staff'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put('/seller/settings/bank', ['bank' => 'bml', 'account_name' => 'Thief', 'account_number' => '7730000999999'])->assertForbidden();
        $this->assertSame('7730000123456', $owner->bankAccount()->value('account_number'));
        $this->put('/seller/settings/holiday', ['on_holiday' => 1])->assertForbidden();
        $this->assertFalse($owner->fresh()->isOnHoliday());
    }

    public function test_staff_of_a_suspended_or_rejected_shop_are_blocked_like_the_owner(): void
    {
        $shop = $this->staffedShop();
        $manager = $this->staffMember($shop, 'manager');
        $this->actingAs($manager)->get('/seller/orders')->assertOk();

        $shop->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($shop)->get('/seller/orders')->assertForbidden();
        $this->actingAs($manager)->get('/seller/orders')->assertForbidden();

        // Rejected: the seller role is gone
        $shop->forceFill(['status' => 'active'])->save();
        $shop->roles()->detach();
        $this->actingAs($manager)->get('/seller/orders')->assertForbidden();

        // A deleted shop has no staff
        $shop->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->actingAs($manager)->get('/seller/orders')->assertOk();
        $shop->delete();
        $this->actingAs($manager)->get('/seller/orders')->assertForbidden();
    }

    public function test_staff_work_on_their_own_shop_only(): void
    {
        $shop = $this->staffedShop('Mine');
        $other = $this->staffedShop('Theirs');
        $manager = $this->staffMember($shop, 'manager');
        $mine = Product::factory()->create(['seller_id' => $shop->id]);
        $theirs = Product::factory()->create(['seller_id' => $other->id, 'stock_quantity' => 5]);
        $theirOrder = Order::factory()->create(['status' => 'pending']);
        $theirOrder->items()->create(['product_id' => $theirs->id, 'quantity' => 1, 'price' => 50]);
        $theirPart = $theirOrder->sellerOrders()->sole();
        $theirCode = $this->shopDiscountCodeFor($other);
        $theirReview = ProductReview::create(['product_id' => $theirs->id, 'reviewer_name' => 'Ali', 'reviewer_email' => 'ali@example.test', 'rating' => 3, 'comment' => 'Fine', 'is_approved' => true]);
        $theirQuestion = ProductQuestion::create(['product_id' => $theirs->id, 'user_id' => User::factory()->create()->id, 'question' => 'Does it come in blue?']);

        $this->actingAs($manager);
        $this->get('/seller/products')->assertOk()->assertSee($mine->sku)->assertDontSee($theirs->sku);
        $this->get('/seller/stock')->assertOk()->assertDontSee($theirs->sku);
        $this->get(route('seller.products.edit', $theirs))->assertForbidden();
        $this->delete(route('seller.products.destroy', $theirs))->assertForbidden();
        $this->post(route('seller.products.duplicate', $theirs))->assertForbidden();
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$theirs->id], 'action' => 'deactivate'])->assertForbidden();
        $this->put('/seller/stock', ['products' => [$theirs->id => 0]])->assertForbidden();
        $this->get('/seller/orders')->assertOk()->assertDontSee($theirOrder->order_number);
        $this->get(route('seller.orders.show', $theirOrder))->assertNotFound();
        $this->post(route('seller.orders.status', $theirOrder), ['status' => 'processing'])->assertNotFound();
        $this->get(route('seller.orders.packing-slip', $theirOrder))->assertNotFound();
        $this->get(route('seller.orders.invoice', $theirOrder))->assertNotFound();
        $this->post(route('seller.orders.messages.store', [$theirOrder, $theirPart]), ['body' => 'Hello'])->assertForbidden();
        $this->post(route('seller.orders.tracking', [$theirOrder, $theirPart]), ['courier' => 'Me'])->assertForbidden();
        $this->get(route('seller.discounts.edit', $theirCode))->assertForbidden();
        $this->post(route('seller.discounts.toggle', $theirCode))->assertForbidden();
        $this->post(route('seller.reviews.reply', $theirReview), ['seller_reply' => 'Thanks!'])->assertForbidden();
        $this->post(route('questions.answer', $theirQuestion), ['answer' => 'Yes'])->assertForbidden();

        $this->assertNotSoftDeleted($theirs);
        $this->assertSame(5, $theirs->fresh()->stock_quantity);
        $this->assertSame('pending', $theirPart->fresh()->status);
        $this->assertTrue($theirCode->fresh()->is_active);
        $this->assertNull($theirReview->fresh()->seller_reply);
        $this->assertNull($theirQuestion->fresh()->answer);
    }

    public function test_what_staff_do_they_do_for_the_shop(): void
    {
        $shop = $this->staffedShop();
        $manager = $this->staffMember($shop, 'manager');
        $packer = $this->staffMember($shop, 'packer');
        $category = Category::factory()->create();

        $this->actingAs($manager)->post('/seller/products', ['name_en' => 'Reef Safe Sunscreen', 'sku' => 'SUN-STAFF', 'category_id' => $category->id, 'price' => 150, 'stock_quantity' => 20])
            ->assertRedirect(route('seller.products.index'));
        $product = Product::where('sku', 'SUN-STAFF')->sole();
        $this->assertSame($shop->id, $product->seller_id);
        $this->assertFalse($product->is_active);

        $this->actingAs($manager)->post('/seller/discounts', ['code' => 'staff10', 'type' => 'percent', 'value' => 10, 'applies_to' => 'all', 'is_active' => 1])->assertRedirect(route('seller.discounts'));
        $this->assertSame($shop->id, ShopDiscountCode::where('code', 'STAFF10')->value('seller_id'));
        // Codes are unique per shop, the shop's (not the staff member's)
        $this->actingAs($manager)->post('/seller/discounts', ['code' => 'STAFF10', 'type' => 'percent', 'value' => 5, 'applies_to' => 'all'])->assertSessionHasErrors('code');

        $this->actingAs($manager)->put('/seller/settings/delivery', ['ships_within_days' => 3])->assertRedirect(route('seller.settings.delivery'));
        $this->assertSame(3, (int) SellerDeliverySetting::where('seller_id', $shop->id)->value('ships_within_days'));
        $this->assertFalse(SellerDeliverySetting::where('seller_id', $manager->id)->exists());

        $this->actingAs($packer)->put('/seller/stock', ['products' => [$product->id => 7]])->assertRedirect();
        $this->assertSame(7, $product->fresh()->stock_quantity);

        $order = Order::factory()->create(['status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 150]);
        $this->actingAs($packer)->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertRedirect();
        $this->assertSame('processing', $order->sellerOrders()->sole()->status);
        $this->actingAs($packer)->get(route('seller.orders.packing-slip', $order))->assertOk()->assertSee($order->order_number);
    }

    public function test_changes_by_staff_are_audited_with_them_as_the_actor(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $packer = $this->staffMember($shop, 'packer');
        $product = Product::factory()->create(['seller_id' => $shop->id, 'stock_quantity' => 3]);
        $order = Order::factory()->create(['status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 80]);

        // Looking is not a change, and a form sent back with errors changed nothing
        $this->actingAs($packer)->get('/seller/stock')->assertOk();
        $this->actingAs($packer)->put('/seller/stock', ['products' => [$product->id => 'lots']])->assertSessionHasErrors();
        $this->assertSame(0, AuditLog::where('action', 'shop.staff_action')->count());

        $this->actingAs($packer)->put('/seller/stock', ['products' => [$product->id => 9]])->assertRedirect();
        $this->actingAs($packer)->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertRedirect();

        $logs = AuditLog::where('action', 'shop.staff_action')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame($packer->id, $log->user_id); // who did it
            $this->assertSame(User::class, $log->subject_type); // for which shop
            $this->assertSame($shop->id, $log->subject_id);
        }
        $this->assertSame(['route' => 'seller.stock.update', 'role' => 'packer', 'shop' => 'Coral Corner'], $logs[0]->changes);
        $this->assertSame(['route' => 'seller.orders.status', 'role' => 'packer', 'shop' => 'Coral Corner', 'order' => $order->id], $logs[1]->changes);

        // The owner's own changes are not staff changes
        $this->actingAs($shop)->put('/seller/stock', ['products' => [$product->id => 4]])->assertRedirect();
        $this->assertSame(2, AuditLog::where('action', 'shop.staff_action')->count());
    }

    public function test_messages_replies_and_answers_record_the_staff_member_but_speak_for_the_shop(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $manager = $this->staffMember($shop, 'manager', ['name' => 'Aminath Rasheed']);
        $packer = $this->staffMember($shop, 'packer', ['name' => 'Ibrahim Waheed']);
        $customer = User::factory()->create(['name' => 'Hawwa Customer']);
        $product = Product::factory()->create(['seller_id' => $shop->id, 'is_active' => true]);
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 90]);
        $part = $order->sellerOrders()->sole();

        // A message from the manager is the shop's, with the manager on record
        $this->actingAs($manager)->post(route('seller.orders.messages.store', [$order, $part]), ['body' => 'Packed and ready to go!'])->assertRedirect();
        $message = \App\Models\Message::sole();
        $this->assertSame($manager->id, $message->sender_id);
        $this->assertSame('seller', $message->sender_role);
        $this->assertSame('Coral Corner', $message->senderName());
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()->assertSee('Packed and ready to go!')->assertDontSee('Aminath Rasheed');

        // The customer answers with a photo; the packer can read the thread but not write, and reading leaves it unread
        Storage::fake('local');
        $this->actingAs($customer)->post(route('orders.messages.store', [$order, $part]), ['body' => 'Thank you so much', 'attachment' => UploadedFile::fake()->image('box.jpg')])->assertRedirect();
        $conversation = Conversation::sole();
        $photo = \App\Models\Message::whereNotNull('attachment_path')->sole();
        $this->actingAs($packer)->get(route('messages.attachment', $photo))->assertOk();
        $this->actingAs($this->staffMember($this->staffedShop('Island Post'), 'manager'))->get(route('messages.attachment', $photo))->assertForbidden();
        $this->assertSame(1, $conversation->fresh()->seller_unread_count);
        $this->actingAs($packer)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Thank you so much')->assertDontSee('name="body"', false);
        $this->assertSame(1, $conversation->fresh()->seller_unread_count);
        $this->actingAs($packer)->post(route('seller.orders.messages.store', [$order, $part]), ['body' => 'Hi'])->assertForbidden();
        $this->assertSame(0, app(MessagingService::class)->unreadCounts($packer)['seller']);
        $this->assertSame(1, app(MessagingService::class)->unreadCounts($manager)['seller']);
        $this->actingAs($manager)->get(route('seller.orders.show', $order))->assertOk()->assertSee('name="body"', false);
        $this->assertSame(0, $conversation->fresh()->seller_unread_count);

        // A reply to a review: written by the manager, signed by the shop
        $review = ProductReview::create(['product_id' => $product->id, 'user_id' => $customer->id, 'reviewer_name' => 'Hawwa', 'reviewer_email' => $customer->email, 'rating' => 5, 'comment' => 'Lovely', 'is_approved' => true]);
        $this->actingAs($manager)->post(route('seller.reviews.reply', $review), ['seller_reply' => 'Thank you for shopping with us'])->assertRedirect();
        $this->assertSame($manager->id, $review->fresh()->seller_reply_user_id);
        $this->assertSame('Coral Corner', $review->fresh()->replyShopName());

        // An answer to a question: by the packer, shown on the product page as the shop's
        $question = ProductQuestion::create(['product_id' => $product->id, 'user_id' => $customer->id, 'question' => 'Is it safe for the reef?']);
        $this->actingAs($packer)->post(route('questions.answer', $question), ['answer' => 'Yes, it is reef safe.'])->assertRedirect();
        $question->refresh();
        $this->assertSame($packer->id, $question->answered_by);
        $this->assertTrue($question->answered_as_shop);
        $this->actingAs($customer)->get(route('products.show', $product))->assertOk()
            ->assertSee('Yes, it is reef safe.')
            ->assertSee('Coral Corner &middot;', false)
            ->assertSee('Reply from Coral Corner')
            ->assertDontSee('Ibrahim Waheed')
            ->assertDontSee('Aminath Rasheed');
    }

    public function test_removing_someone_or_changing_their_role_works_from_their_next_request(): void
    {
        $shop = $this->staffedShop();
        $manager = $this->staffMember($shop, 'manager');
        $member = ShopStaff::where('user_id', $manager->id)->sole();
        $this->actingAs($manager)->get('/seller/products')->assertOk();

        $this->actingAs($shop)->put(route('seller.staff.update', $member), ['role' => 'packer'])->assertRedirect(route('seller.staff'));
        $this->actingAs($manager)->get('/seller/products')->assertForbidden();
        $this->actingAs($manager)->get('/seller/orders')->assertOk();

        $this->actingAs($shop)->delete(route('seller.staff.destroy', $member))->assertRedirect(route('seller.staff'));
        $this->assertModelMissing($member);
        $this->actingAs($manager)->get('/seller/orders')->assertForbidden();
        // Still a customer: the rest of iruali works, the Seller Centre link is gone
        $this->actingAs($manager)->get('/account')->assertOk()->assertDontSee('Seller Centre');

        $this->assertSame(['shop_staff.role_changed', 'shop_staff.removed'], AuditLog::where('user_id', $shop->id)->orderBy('id')->pluck('action')->all());
        $this->assertSame(['user_id' => $manager->id, 'from' => 'manager', 'to' => 'packer'], AuditLog::where('action', 'shop_staff.role_changed')->sole()->changes);
    }

    public function test_staff_still_shop_on_iruali_with_their_own_cart_and_orders(): void
    {
        $this->enableBml();
        $shop = $this->staffedShop('Coral Corner');
        $packer = $this->staffMember($shop, 'packer');
        $elsewhere = $this->staffedShop('Island Post');
        $product = Product::factory()->create(['seller_id' => $elsewhere->id, 'price' => 120, 'stock_quantity' => 5, 'is_active' => true]);

        $this->actingAs($packer)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $cart = Cart::where('user_id', $packer->id)->where('status', 'active')->sole();
        $this->assertSame(2, (int) $cart->items()->sole()->quantity);

        $result = app(OrderService::class)->createOrderFromCart($packer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '20026', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ], $cart);
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $order = $result['order'];
        $this->assertSame($packer->id, $order->user_id);

        $this->actingAs($packer)->get('/orders')->assertOk()->assertSee($order->order_number);
        $this->actingAs($packer)->get(route('orders.show', $order))->assertOk();
        // Their own order is not the shop's business, and the shop's orders are not theirs as a customer
        $this->actingAs($packer)->get('/seller/orders')->assertOk()->assertDontSee($order->order_number);
        $this->assertSame(0, Order::where('user_id', $shop->id)->count());
    }

    public function test_the_owner_can_ask_staff_to_use_two_step_sign_in(): void
    {
        config(['staff.require_two_factor' => true]); // iruali's own staff rule is separate and unchanged
        $shop = $this->staffedShop('Coral Corner');
        $manager = $this->staffMember($shop, 'manager');

        $this->actingAs($shop)->put(route('seller.staff.settings'), ['staff_require_two_factor' => 1])->assertRedirect(route('seller.staff'));
        $this->assertTrue((bool) $shop->fresh()->staff_require_two_factor);
        $this->assertSame($shop->id, AuditLog::where('action', 'shop_staff.two_factor')->sole()->user_id);

        $this->actingAs($manager)->get('/seller/orders')->assertRedirect(route('profile.2fa.setup'))->assertSessionHas('warning');
        $this->actingAs($manager)->get('/profile/2fa/setup')->assertOk();
        $this->actingAs($shop)->get('/seller/staff')->assertOk()->assertSee('Two-step sign-in not set up yet');

        $manager->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('secret')])->save();
        $this->actingAs($manager->fresh())->get('/seller/orders')->assertOk();
        $this->actingAs($shop)->get('/seller/orders')->assertOk(); // owners are not asked

        $this->actingAs($shop)->put(route('seller.staff.settings'), ['staff_require_two_factor' => 0])->assertRedirect();
        $this->assertFalse((bool) $shop->fresh()->staff_require_two_factor);
    }

    public function test_a_staff_account_cannot_open_a_shop_of_its_own(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $manager = $this->staffMember($shop, 'manager');

        $this->actingAs($manager)->get('/seller/apply')->assertRedirect(route('seller.dashboard'))->assertSessionHas('error');
        $this->actingAs($manager)->post('/seller/apply', [
            'business_name' => 'My Own Shop', 'business_description' => 'Handmade things from Addu, all made by me.', 'phone' => '7712345',
            'address' => 'Hithadhoo', 'city' => 'Addu', 'agree_seller_terms' => 1,
        ])->assertRedirect(route('seller.dashboard'));
        $this->assertFalse($manager->fresh()->hasRole('seller'));
        $this->assertFalse((bool) $manager->fresh()->is_seller);
    }
}

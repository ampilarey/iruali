<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\ShopDiscountCode;
use App\Models\ShopDiscountRedemption;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use App\Services\ShopDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsShopDeals;
use Tests\TestCase;

/**
 * Shop discount codes: the shop's own codes, applied in the cart to that shop's items, paid for by
 * the shop (Seller Centre → Discount codes).
 */
class ShopDiscountCodesTest extends TestCase
{
    use BuildsShopDeals, RefreshDatabase;

    protected User $customer;

    protected User $shopA;

    protected User $shopB;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
        Setting::set(['default_commission_rate' => 10]);

        $this->customer = User::factory()->create(['loyalty_points' => 0, 'email' => 'aisha@example.com']);
        $this->shopA = $this->shop('Island Crafts', 15);
        $this->shopB = $this->shop('Reefline Marine');
    }

    // ---- Seller Centre --------------------------------------------------------------------------

    public function test_a_shop_creates_edits_and_pauses_codes_and_sees_only_its_own(): void
    {
        $product = $this->productOf($this->shopA, 100);

        $this->actingAs($this->shopA)->get(route('seller.discounts'))->assertOk()->assertSee('You have no discount codes yet.');
        $this->get(route('seller.discounts.create'))->assertOk()->assertSee('name="code"', false);

        $this->post(route('seller.discounts.store'), [
            'code' => ' save10 ', 'type' => 'percent', 'value' => '10', 'min_spend' => '50',
            'starts_at' => '', 'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'max_uses' => '100', 'max_uses_per_customer' => '1', 'applies_to' => 'all', 'is_active' => '1',
        ])->assertRedirect(route('seller.discounts'))->assertSessionHasNoErrors();

        $code = ShopDiscountCode::sole();
        $this->assertSame('SAVE10', $code->code, 'kept in capitals');
        $this->assertSame($this->shopA->id, $code->seller_id);
        $this->assertEquals(50, $code->min_spend);
        $this->assertTrue($code->is_active);

        // Unique per shop, whatever the case; another shop may use the same code
        $this->post(route('seller.discounts.store'), ['code' => 'Save10', 'type' => 'fixed', 'value' => '20', 'applies_to' => 'all'])->assertSessionHasErrors('code');
        $this->post(route('seller.discounts.store'), ['code' => 'BAD CODE', 'type' => 'fixed', 'value' => '20', 'applies_to' => 'all'])->assertSessionHasErrors('code');
        $this->post(route('seller.discounts.store'), ['code' => 'HALF', 'type' => 'percent', 'value' => '95', 'applies_to' => 'all'])->assertSessionHasErrors('value');
        $this->post(route('seller.discounts.store'), ['code' => 'DATES', 'type' => 'fixed', 'value' => '5', 'applies_to' => 'all', 'starts_at' => '2026-12-10T10:00', 'ends_at' => '2026-12-01T10:00'])->assertSessionHasErrors('ends_at');
        $this->post(route('seller.discounts.store'), ['code' => 'PICK', 'type' => 'fixed', 'value' => '5', 'applies_to' => 'selected'])->assertSessionHasErrors('product_ids');

        $other = $this->productOf($this->shopB, 40);
        $this->post(route('seller.discounts.store'), ['code' => 'PICK', 'type' => 'fixed', 'value' => '5', 'applies_to' => 'selected', 'product_ids' => [$other->id]])->assertSessionHasErrors('product_ids');
        $this->post(route('seller.discounts.store'), ['code' => 'PICK', 'type' => 'fixed', 'value' => '5', 'applies_to' => 'selected', 'product_ids' => [$product->id]])->assertSessionHasNoErrors();
        $this->assertSame([$product->id], ShopDiscountCode::where('code', 'PICK')->sole()->products()->pluck('products.id')->all());

        $this->actingAs($this->shopB)->post(route('seller.discounts.store'), ['code' => 'save10', 'type' => 'fixed', 'value' => '20', 'applies_to' => 'all'])->assertSessionHasNoErrors();
        $this->assertSame(2, ShopDiscountCode::where('code', 'SAVE10')->count());
        $theirs = ShopDiscountCode::where('seller_id', $this->shopB->id)->sole();

        // Edit and pause
        $this->actingAs($this->shopA)->get(route('seller.discounts.edit', $code))->assertOk()->assertSee('SAVE10');
        $this->put(route('seller.discounts.update', $code), ['code' => 'SAVE15', 'type' => 'percent', 'value' => '15', 'applies_to' => 'all', 'is_active' => '1'])
            ->assertRedirect(route('seller.discounts'));
        $this->assertSame('SAVE15', $code->fresh()->code);
        $this->assertEquals(15, $code->fresh()->value);

        $this->post(route('seller.discounts.toggle', $code))->assertRedirect();
        $this->assertFalse($code->fresh()->is_active);
        $this->get(route('seller.discounts'))->assertOk()->assertSee('SAVE15')->assertSee('Paused')->assertSee('Resume')
            ->assertSee('PICK')->assertDontSee('MVR 20.00 off');
        $this->post(route('seller.discounts.toggle', $code));
        $this->assertTrue($code->fresh()->is_active);

        // Another shop's code can't be opened, changed or paused; customers have no Seller Centre
        $this->get(route('seller.discounts.edit', $theirs))->assertForbidden();
        $this->put(route('seller.discounts.update', $theirs), ['code' => 'MINE', 'type' => 'percent', 'value' => '50', 'applies_to' => 'all'])->assertForbidden();
        $this->post(route('seller.discounts.toggle', $theirs))->assertForbidden();
        $this->assertSame('SAVE10', $theirs->fresh()->code);
        $this->assertTrue($theirs->fresh()->is_active);
        $this->actingAs($this->customer)->get(route('seller.discounts'))->assertForbidden();
    }

    public function test_the_codes_page_shows_uses_and_sales(): void
    {
        $product = $this->productOf($this->shopA, 100);
        $code = $this->codeFor($this->shopA, 'REEF10', ['max_uses' => 5]);

        $this->actingAs($this->customer);
        $this->cartFor($this->customer, [[$product, 2]]);
        $this->useShopCodes([$this->shopA->id => 'reef10']);
        $order = $this->placeOrder($this->customer);

        $use = ShopDiscountRedemption::sole();
        $this->assertSame($code->id, $use->shop_discount_code_id);
        $this->assertSame('REEF10', $use->code);
        $this->assertSame($order->id, $use->order_id);
        $this->assertSame($this->customer->id, $use->user_id);
        $this->assertSame('aisha@example.com', $use->email);
        $this->assertEquals(20, $use->amount);
        $this->assertEquals(180, $use->sales);

        $page = $this->actingAs($this->shopA)->get(route('seller.discounts'))->assertOk();
        $page->assertSee('REEF10')->assertSee('MVR 20.00')->assertSee('MVR 180.00');
        $this->assertSame(['uses' => 1, 'discount' => 20.0, 'sales' => 180.0], app(ShopDiscountService::class)->stats(ShopDiscountCode::all())[$code->id]);

        // A cancelled order no longer counts
        app(OrderService::class)->updateOrderStatus($order, 'cancelled');
        $this->assertSame(0, app(ShopDiscountService::class)->stats(ShopDiscountCode::all())[$code->id]['uses']);
    }

    // ---- Cart -----------------------------------------------------------------------------------

    public function test_percent_and_fixed_codes_apply_only_to_their_shops_items_one_per_shop(): void
    {
        $a = $this->productOf($this->shopA, 100);
        $b = $this->productOf($this->shopB, 80);
        $this->codeFor($this->shopA, 'REEF10');
        $this->codeFor($this->shopA, 'REEF20', ['value' => 20]);
        $this->codeFor($this->shopB, 'TAKE15', ['type' => 'fixed', 'value' => 15]);
        $this->cartFor($this->customer, [[$a, 2], [$b, 1]]);

        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'reef10'])->assertSessionHasNoErrors();
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'take15'])->assertSessionHasNoErrors();
        $this->assertSame([$this->shopA->id => 'REEF10', $this->shopB->id => 'TAKE15'], app(ShopDiscountService::class)->appliedCodes());

        $this->get(route('cart'))->assertOk()
            ->assertSee('Shop code REEF10 (Island Crafts)')->assertSee('MVR 20.00')
            ->assertSee('Shop code TAKE15 (Reefline Marine)')->assertSee('MVR 15.00')
            ->assertSee('MVR 245.00'); // 280 - 20 - 15

        // A second code for the same shop replaces the first
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF20'])->assertSessionHasNoErrors();
        $this->assertSame('REEF20', app(ShopDiscountService::class)->appliedCodes()[$this->shopA->id]);

        $order = $this->placeOrder($this->customer);
        $this->assertEquals(55, $order->shop_discount); // A: 20% of 200, B: 15
        $this->assertEquals(225 + 75, $order->total_amount); // 280 - 40 - 15, plus delivery to the islands
        $partA = $order->sellerOrders()->where('seller_id', $this->shopA->id)->sole();
        $partB = $order->sellerOrders()->where('seller_id', $this->shopB->id)->sole();
        $this->assertEquals(40, $partA->shop_discount);
        $this->assertEquals(15, $partB->shop_discount);
        $this->assertOrderAddsUp($order);
        $this->assertSame([], app(ShopDiscountService::class)->appliedCodes(), 'the codes are used up with the cart');

        // Removing a code from the cart
        $this->cartFor($this->customer, [[$a, 1]]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF10']);
        $this->post(route('cart.shopCode.remove'), ['seller_id' => $this->shopA->id])->assertRedirect();
        $this->assertSame([], app(ShopDiscountService::class)->appliedCodes());
    }

    public function test_a_fixed_code_never_takes_more_than_the_items_and_totals_never_go_below_zero(): void
    {
        $b = $this->productOf($this->shopB, 40);
        $this->codeFor($this->shopB, 'BIG', ['type' => 'fixed', 'value' => 100]);
        Voucher::factory()->create(['code' => 'IRU20', 'type' => 'fixed', 'amount' => 20, 'is_active' => true, 'min_order' => null, 'max_uses' => null, 'valid_from' => null, 'valid_until' => null]);
        $this->cartFor($this->customer, [[$b, 1]]);

        $this->actingAs($this->customer);
        $this->useShopCodes([$this->shopB->id => 'BIG']);
        session(['voucher_code' => 'IRU20']);

        $order = $this->placeOrder($this->customer);
        $this->assertEquals(40, $order->shop_discount, 'at most what the items cost');
        $this->assertEquals(0, $order->voucher_discount, 'nothing left for the voucher');
        $this->assertEquals(75, $order->total_amount, 'only the delivery is left to pay');
        $this->assertOrderAddsUp($order);
    }

    public function test_bad_paused_not_started_expired_and_minimum_spend_codes_get_clear_messages(): void
    {
        $a = $this->productOf($this->shopA, 60);
        $this->codeFor($this->shopA, 'PAUSED', ['is_active' => false]);
        $this->codeFor($this->shopA, 'LATER', ['starts_at' => now()->addDays(2)]);
        $this->codeFor($this->shopA, 'OLD', ['ends_at' => now()->subMinute()]);
        $this->codeFor($this->shopA, 'BIGSPEND', ['min_spend' => 150]);
        $this->codeFor($this->shopB, 'ELSEWHERE'); // shop B has nothing in the cart
        Voucher::factory()->create(['code' => 'IRUALI5', 'is_active' => true]);
        $this->cartFor($this->customer, [[$a, 2]]);

        $this->actingAs($this->customer);
        $error = fn (string $code) => $this->post(route('cart.shopCode.apply'), ['shop_code' => $code])->assertSessionHasErrors('shop_code')->baseResponse;
        $message = fn () => session('errors')->first('shop_code');

        $error('NOPE');
        $this->assertSame('The shop code NOPE is not valid for anything in your cart.', $message());
        $error('ELSEWHERE');
        $this->assertSame('The shop code ELSEWHERE is not valid for anything in your cart.', $message());
        $error('iruali5');
        $this->assertSame('That is an iruali voucher. Please enter it under "Voucher code".', $message());
        $error('paused');
        $this->assertSame('The shop code PAUSED is not active right now.', $message());
        $error('later');
        $this->assertStringStartsWith('The shop code LATER can be used from ', $message());
        $error('old');
        $this->assertSame('The shop code OLD has expired.', $message());
        $error('bigspend');
        $this->assertSame('Spend MVR 150.00 on eligible items from Island Crafts to use the code BIGSPEND (add MVR 30.00 more).', $message());

        $this->assertSame([], app(ShopDiscountService::class)->appliedCodes());
        $this->assertSame(0, Order::count());
    }

    public function test_minimum_spend_and_selected_products_count_only_the_eligible_items(): void
    {
        $mug = $this->productOf($this->shopA, 40);
        $bowl = $this->productOf($this->shopA, 120);
        $code = $this->codeFor($this->shopA, 'MUGS', ['type' => 'percent', 'value' => 25, 'min_spend' => 100, 'applies_to' => 'selected']);
        $code->products()->sync([$mug->id]);
        $this->cartFor($this->customer, [[$mug, 2], [$bowl, 1]]);

        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'MUGS'])->assertSessionHasErrors('shop_code');
        $this->assertStringContainsString('add MVR 20.00 more', session('errors')->first('shop_code'), 'only the mugs (80) count towards the 100');

        \App\Models\CartItem::where('product_id', $mug->id)->update(['quantity' => 3]); // 120 of mugs now
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'MUGS'])->assertSessionHasNoErrors();

        $order = $this->placeOrder($this->customer);
        $items = $order->items->keyBy('product_id');
        $this->assertEquals(30, $items[$mug->id]->shop_code_discount, '25% of the mugs only');
        $this->assertEquals(0, $items[$bowl->id]->shop_code_discount);
        $this->assertEquals(30, $order->shop_discount);
        $this->assertOrderAddsUp($order);
    }

    public function test_total_and_per_customer_limits_count_accounts_and_guest_emails(): void
    {
        $a = $this->productOf($this->shopA, 50);
        $once = $this->codeFor($this->shopA, 'ONCE', ['max_uses_per_customer' => 1]);
        $this->codeFor($this->shopA, 'TWOTOTAL', ['max_uses' => 2]);

        // The account uses it once; a second time is refused in the cart
        $this->actingAs($this->customer);
        $this->cartFor($this->customer, [[$a, 1]]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'ONCE'])->assertSessionHasNoErrors();
        $this->placeOrder($this->customer);

        $this->cartFor($this->customer, [[$a, 1]]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'ONCE'])->assertSessionHasErrors('shop_code');
        $this->assertSame('You have already used the shop code ONCE as many times as it allows.', session('errors')->first('shop_code'));

        // A guest is limited by the email given at checkout (also the account's own email)
        auth()->logout();
        $guestCart = $this->cartFor(null, [[$a, 1]]);
        $this->useShopCodes([$this->shopA->id => 'ONCE']);
        $refused = $this->checkout(null, $guestCart, ['email' => 'AISHA@example.com', 'name' => 'Aisha']);
        $this->assertFalse($refused['success']);
        $this->assertStringContainsString('You have already used the shop code ONCE', $refused['message']);
        $this->assertSame(1, Order::count(), 'nothing was placed');

        $this->useShopCodes([$this->shopA->id => 'ONCE']);
        $this->placeOrder(null, $guestCart, ['email' => 'Mariyam@Example.com', 'name' => 'Mariyam']);
        $this->assertSame('mariyam@example.com', ShopDiscountRedemption::latest('id')->first()->email);

        $guestAgain = $this->cartFor(null, [[$a, 1]]);
        $this->useShopCodes([$this->shopA->id => 'ONCE']);
        $again = $this->checkout(null, $guestAgain, ['email' => 'mariyam@example.com', 'name' => 'Mariyam']);
        $this->assertFalse($again['success'], 'the same guest email again');
        $this->assertSame(2, $once->redemptions()->count());

        // The total limit counts everyone
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();
        foreach ([$first, $second] as $customer) {
            $this->actingAs($customer);
            $this->cartFor($customer, [[$a, 1]]);
            $this->post(route('cart.shopCode.apply'), ['shop_code' => 'TWOTOTAL'])->assertSessionHasNoErrors();
            $this->placeOrder($customer);
        }
        $this->actingAs($third);
        $this->cartFor($third, [[$a, 1]]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'TWOTOTAL'])->assertSessionHasErrors('shop_code');
        $this->assertSame('The shop code TWOTOTAL has been used the most times it can be.', session('errors')->first('shop_code'));
    }

    public function test_a_guest_applies_a_code_in_the_cart_and_checkout_limits_it_by_email(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $a = $this->productOf($this->shopA, 100);
        $this->codeFor($this->shopA, 'HELLO', ['max_uses_per_customer' => 1]);
        $fields = fn (string $email) => $this->shippingFields() + [
            'guest_email' => $email, 'guest_name' => 'Mariyam', 'delivery_zone' => 'islands', 'agree_terms' => '1', 'shipping_phone' => '777 1234',
        ];

        // A guest's cart (keyed by the session's cart token): the code goes on in the cart
        $this->post(route('cart.add'), ['product_id' => $a->id, 'quantity' => 1])->assertRedirect(route('cart'));
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'hello'])->assertSessionHasNoErrors();
        $this->get(route('checkout.guest'))->assertOk()->assertSee('Shop code HELLO (Island Crafts)')->assertSee('MVR 165.00');
        $this->post(route('checkout.guest.store'), $fields('Mariyam@Example.com'))->assertRedirect('https://pay.bml.test/txn_test');
        $this->assertEquals(10, Order::sole()->shop_discount);
        $this->assertSame('mariyam@example.com', ShopDiscountRedemption::sole()->email);

        // The same email again: refused at checkout, since a guest is only known by email there
        $this->post(route('cart.add'), ['product_id' => $a->id, 'quantity' => 1]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'HELLO'])->assertSessionHasNoErrors();
        $this->post(route('checkout.guest.store'), $fields('mariyam@example.com'))->assertRedirect(route('cart'));
        $this->assertStringContainsString('You have already used the shop code HELLO', session('notification')['message']);
        $this->assertSame(1, Order::count());

        // Another email can use it
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'HELLO'])->assertSessionHasNoErrors();
        $this->post(route('checkout.guest.store'), $fields('fathimath@example.com'))->assertRedirect(); // (the fake BML hands out one transaction id only)
        $this->assertSame(2, Order::count());
        $this->assertSame(2, ShopDiscountRedemption::count());
    }

    public function test_the_last_use_cannot_be_taken_twice_and_a_cancelled_order_gives_it_back(): void
    {
        $a = $this->productOf($this->shopA, 50);
        $this->codeFor($this->shopA, 'LAST', ['max_uses' => 1]);
        $other = User::factory()->create();

        // Both carts have the code while one use is left
        $this->cartFor($this->customer, [[$a, 1]]);
        $this->cartFor($other, [[$a, 1]]);

        $this->actingAs($this->customer);
        $this->useShopCodes([$this->shopA->id => 'LAST']);
        $first = $this->placeOrder($this->customer);

        $this->actingAs($other);
        $this->useShopCodes([$this->shopA->id => 'LAST']);
        $second = $this->checkout($other);
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('has been used the most times it can be', $second['message']);
        $this->assertSame(1, Order::count());
        $this->assertSame(49, $a->fresh()->stock_quantity, 'the refused order took no stock');
        $code = ShopDiscountCode::where('code', 'LAST')->sole();
        $this->assertSame(1, app(ShopDiscountService::class)->usesCount($code, true), 'the locking count checkout uses');
        $this->assertSame(1, app(ShopDiscountService::class)->customerUses($code, $this->customer, null, true));
        $this->assertSame(0, app(ShopDiscountService::class)->customerUses($code, $other, null, true));

        // Cancelling the first order frees the use
        app(OrderService::class)->updateOrderStatus($first, 'cancelled');
        $this->assertSame(0, app(ShopDiscountService::class)->usesCount($code, true));
        $this->assertSame(0, app(ShopDiscountService::class)->usesCount($code));
        $this->useShopCodes([$this->shopA->id => 'LAST']);
        $order = $this->placeOrder($other);
        $this->assertEquals(5, $order->shop_discount);
    }

    public function test_a_code_that_expires_between_cart_and_checkout_is_refused_cleanly(): void
    {
        $a = $this->productOf($this->shopA, 200, ['stock_quantity' => 5]);
        $this->codeFor($this->shopA, 'WEEKEND', ['type' => 'fixed', 'value' => 30, 'ends_at' => now()->addHour()]);
        $this->cartFor($this->customer, [[$a, 1]]);

        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'weekend'])->assertSessionHasNoErrors();
        $this->get(route('checkout'))->assertOk()->assertSee('Shop code WEEKEND (Island Crafts)')->assertSee('MVR 245.00'); // 170 + 75 delivery

        $this->travel(2)->hours(); // it ended while the customer was paying attention elsewhere

        $fields = $this->shippingFields() + ['agree_terms' => '1', 'delivery_zone' => 'islands'];
        $this->post(route('orders.store'), $fields)->assertRedirect(route('cart'));
        $this->assertStringContainsString('The shop code WEEKEND has expired.', session('notification')['message']);
        $this->assertStringContainsString('Your order was not placed', session('notification')['message']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, ShopDiscountRedemption::count());
        $this->assertSame(5, $a->fresh()->stock_quantity);
        $this->assertSame([], app(ShopDiscountService::class)->appliedCodes(), 'the code is off the cart');

        // The cart shows the new total; ordering again goes through at the full price
        $this->get(route('cart'))->assertOk()->assertDontSee('Shop code WEEKEND')->assertSee('MVR 200.00');
        $this->post(route('orders.store'), $fields)->assertRedirect('https://pay.bml.test/txn_test');
        $order = Order::sole();
        $this->assertEquals(275, $order->total_amount);
        $this->assertEquals(0, $order->shop_discount);
    }

    public function test_a_code_that_stops_working_before_checkout_is_shown_as_removed(): void
    {
        $a = $this->productOf($this->shopA, 200);
        $code = $this->codeFor($this->shopA, 'FLASH', ['type' => 'fixed', 'value' => 30]);
        $this->cartFor($this->customer, [[$a, 1]]);

        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'FLASH'])->assertSessionHasNoErrors();
        $code->update(['is_active' => false]); // the shop pauses it

        $this->get(route('checkout'))->assertOk()
            ->assertSee('A shop code was removed: The shop code FLASH is not active right now.')
            ->assertDontSee('Shop code FLASH (Island Crafts)')
            ->assertSee('MVR 275.00');
        $this->assertSame([], app(ShopDiscountService::class)->appliedCodes());
    }

    // ---- With iruali vouchers and points ---------------------------------------------------------

    public function test_shop_codes_come_first_then_the_iruali_voucher_on_what_is_left_then_points(): void
    {
        $a1 = $this->productOf($this->shopA, 33.33);
        $a2 = $this->productOf($this->shopA, 19.99);
        $b = $this->productOf($this->shopB, 100);
        $this->codeFor($this->shopA, 'REEF10'); // 10%
        Voucher::factory()->create(['code' => 'IRU5', 'type' => 'percent', 'amount' => 5, 'is_active' => true, 'min_order' => 200, 'max_uses' => null, 'valid_from' => null, 'valid_until' => null]);
        $this->customer->forceFill(['loyalty_points' => 1000])->save();
        $this->cartFor($this->customer, [[$a1, 3], [$a2, 1], [$b, 1]]);

        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF10'])->assertSessionHasNoErrors();
        $this->post(route('cart.applyVoucher'), ['voucher_code' => 'IRU5'])->assertSessionHasNoErrors();
        $this->post(route('checkout.redeemPoints'), ['points' => 50])->assertSessionHasNoErrors();

        // Cart: 219.98 - 12.00 (10% of shop A's 119.98) = 207.98; voucher 5% of that = 10.40
        $summary = app(\App\Services\CartService::class)->getCartSummary(app(\App\Services\CartService::class)->currentCart());
        $this->assertEquals(219.98, $summary['subtotal']);
        $this->assertEquals(12.00, $summary['shop_discount']);
        $this->assertEquals(12.00, $summary['shop_code_discount']);
        $this->assertEquals(0, $summary['multibuy_discount']);
        $this->assertEquals(10.40, $summary['voucher_discount']);
        $this->assertEquals(147.58, $summary['total']); // 207.98 - 10.40 - 50 points

        $order = $this->placeOrder($this->customer);
        $this->assertEquals(12.00, $order->shop_discount);
        $this->assertEquals(10.40, $order->voucher_discount);
        $this->assertSame(50, (int) $order->points_redeemed);
        $this->assertEquals(222.58, $order->total_amount); // 147.58 + 75 delivery
        $this->assertSame(1, $order->loyalty_points_earned, 'points are earned on what was paid for the goods');

        // The code's 12.00 is shared over shop A's lines in proportion, to the laari
        $items = $order->items->keyBy('product_id');
        $this->assertEquals(10.00, $items[$a1->id]->shop_code_discount);
        $this->assertEquals(2.00, $items[$a2->id]->shop_code_discount);
        $this->assertEquals(0, $items[$b->id]->shop_code_discount);
        $this->assertOrderAddsUp($order);

        // The voucher's minimum is checked on what is left after the shops' discounts
        $this->cartFor($this->customer, [[$a1, 3], [$a2, 1], [$b, 1]]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF10']);
        $this->codeFor($this->shopB, 'BIGB', ['type' => 'fixed', 'value' => 10]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'BIGB'])->assertSessionHasNoErrors(); // 197.98 left
        $this->post(route('cart.applyVoucher'), ['voucher_code' => 'IRU5'])->assertSessionHasErrors('voucher_code');
    }

    public function test_points_are_capped_by_what_is_left_after_the_shop_and_voucher_discounts(): void
    {
        $a = $this->productOf($this->shopA, 100);
        $this->codeFor($this->shopA, 'HALF', ['value' => 50]);
        $this->customer->forceFill(['loyalty_points' => 500])->save();
        $this->cartFor($this->customer, [[$a, 1]]);

        $this->actingAs($this->customer);
        $this->useShopCodes([$this->shopA->id => 'HALF']);
        session(['points_redeemed' => 100]); // redeemed against the full price earlier

        $order = $this->placeOrder($this->customer);
        $this->assertSame(50, (int) $order->points_redeemed, 'only what the goods still cost');
        $this->assertEquals(75, $order->total_amount, 'goods paid in full by points; delivery left');
        $this->assertSame(450, $this->customer->fresh()->loyalty_points);
        $this->assertOrderAddsUp($order);
    }

    // ---- Money: earnings, payouts, rounding ----------------------------------------------------

    public function test_earnings_and_commission_are_on_the_discounted_total_and_the_payout_follows(): void
    {
        $a1 = $this->productOf($this->shopA, 33.33);
        $a2 = $this->productOf($this->shopA, 19.99);
        $b = $this->productOf($this->shopB, 100);
        $this->codeFor($this->shopA, 'REEF10');
        $this->cartFor($this->customer, [[$a1, 3], [$a2, 1], [$b, 1]]);

        $this->actingAs($this->customer);
        $this->useShopCodes([$this->shopA->id => 'REEF10']);
        $order = $this->placeOrder($this->customer);

        $partA = $order->sellerOrders()->where('seller_id', $this->shopA->id)->sole();
        $this->assertEquals(119.98, $partA->subtotal);
        $this->assertEquals(12.00, $partA->shop_discount);
        $this->assertEquals(107.98, $partA->netSubtotal());
        $this->assertEquals(16.20, $partA->commission_amount, '15% of 107.98');
        $this->assertEquals(91.78, $partA->seller_earnings);
        $partB = $order->sellerOrders()->where('seller_id', $this->shopB->id)->sole();
        $this->assertEquals(0, $partB->shop_discount);
        $this->assertEquals(90, $partB->seller_earnings);

        // Delivered and paid: the payout is the discounted earnings
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
        app(\App\Services\PaymentService::class)->confirm($order->fresh());
        $payout = app(\App\Services\PayoutService::class)->createPayout($this->shopA, null, 'BML-1', null, $admin);
        $this->assertEquals(91.78, $payout->amount);

        // The shop sees its discount on the order
        $this->actingAs($this->shopA)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('Your discounts (multi-buy, codes)')->assertSee('MVR 12.00')->assertSee('MVR 91.78');
        // The customer's order page and receipt show it too
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertSee('Shop code REEF10 (Island Crafts)')->assertSee('MVR 207.98');
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Shop code REEF10 (Island Crafts)');
    }

    public function test_line_amounts_add_up_to_the_order_total_and_the_bml_amount(): void
    {
        $shopC = $this->shop('Atoll Spices', 12.5);
        $products = [
            [$this->productOf($this->shopA, 7.77), 3],
            [$this->productOf($this->shopA, 13.13), 2],
            [$this->productOf($this->shopA, 0.99), 7],
            [$this->productOf($this->shopB, 45.45), 1],
            [$this->productOf($this->shopB, 2.22), 9],
            [$this->productOf($shopC, 99.99), 1],
            [$this->productOf($shopC, 1.01), 3],
        ];
        $this->offerFor($this->shopA, [['min_qty' => 2, 'percent' => 3.5]], [$products[1][0]]);
        $this->codeFor($this->shopA, 'ODD', ['value' => 7.25]);
        $this->codeFor($this->shopB, 'THIRD', ['value' => 33.33]);
        $this->codeFor($shopC, 'FIXED', ['type' => 'fixed', 'value' => 10.01]);
        Voucher::factory()->create(['code' => 'IRU', 'type' => 'percent', 'amount' => 7, 'is_active' => true, 'min_order' => null, 'max_uses' => null, 'valid_from' => null, 'valid_until' => null]);
        $this->customer->forceFill(['loyalty_points' => 13])->save();
        $this->cartFor($this->customer, $products);

        $this->actingAs($this->customer);
        $this->useShopCodes([$this->shopA->id => 'ODD', $this->shopB->id => 'THIRD', $shopC->id => 'FIXED']);
        session(['voucher_code' => 'IRU', 'points_redeemed' => 13]);

        $deals = app(ShopDiscountService::class)->evaluate(app(\App\Services\CartService::class)->currentCart(), app(ShopDiscountService::class)->appliedCodes(), $this->customer);
        foreach ($deals['codes'] as $sellerId => $applied) {
            $shares = array_sum(array_map(fn ($line) => $this->laari($line['code']), array_filter($deals['lines'], fn ($line) => $line['seller_id'] === $sellerId)));
            $this->assertSame($this->laari($applied['amount']), $shares, 'the shares add up to the code exactly');
        }

        $order = $this->placeOrder($this->customer);
        $this->assertOrderAddsUp($order);
        $this->assertGreaterThan(0, (float) $order->voucher_discount);

        // BML is asked for exactly the order total
        $this->post(route('payments.bml.pay', $order));
        $this->assertSame($this->laari($order->total_amount), $order->paymentTransactions()->sole()->amount);
    }

    // ---- Returns --------------------------------------------------------------------------------

    public function test_a_partial_return_refunds_what_the_customer_paid_for_the_units(): void
    {
        Setting::set(['return_window_days' => 7]);
        $a = $this->productOf($this->shopA, 10);
        $this->codeFor($this->shopA, 'ONEOFF', ['type' => 'fixed', 'value' => 1]);
        $this->cartFor($this->customer, [[$a, 3]]);

        $this->actingAs($this->customer);
        $this->useShopCodes([$this->shopA->id => 'ONEOFF']);
        $order = $this->placeOrder($this->customer);
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
        app(\App\Services\PaymentService::class)->confirm($order->fresh());
        $part = $order->sellerOrders()->sole();
        $item = $order->items()->sole();
        $this->assertEquals(29, $item->netTotal());

        // One of three: 29.00 / 3 = 9.67
        $this->actingAs($this->customer)->post(route('orders.returns.store', [$order, $part]), ['quantities' => [$item->id => 1], 'reason' => 'change_of_mind', 'details' => 'Too small.'])->assertSessionHasNoErrors();
        $first = \App\Models\ReturnRequest::sole();
        $this->assertEquals(9.67, $first->items_value);
        $this->assertEquals(9.67, app(\App\Services\ReturnService::class)->suggestedRefund($first));

        $this->actingAs($admin)->post(route('admin.returns.approve', $first), ['refund_amount' => '9.67', 'restock' => '1']);
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertEquals(-8.22, \App\Models\SellerAdjustment::sole()->amount, '85% of 9.67, the shop\'s share of what was paid');
        app(\App\Services\ReturnService::class)->markRefunded($first->fresh(), 'BML-R1');

        // The other two: together the three refunds are exactly the 29.00 paid
        $this->actingAs($this->customer)->post(route('orders.returns.store', [$order, $part]), ['quantities' => [$item->id => 2], 'reason' => 'change_of_mind', 'details' => 'Too small too.'])->assertSessionHasNoErrors();
        $second = \App\Models\ReturnRequest::latest('id')->first();
        $this->assertEquals(19.33, $second->items_value);
        $this->assertSame(2900, $this->laari($first->items_value) + $this->laari($second->items_value));
    }
}

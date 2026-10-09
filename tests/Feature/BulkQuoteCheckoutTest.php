<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\QuoteAccepted;
use App\Notifications\QuoteWithdrawn;
use App\Services\CartService;
use App\Services\DiscountService;
use App\Services\ShopDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsQuotes;
use Tests\TestCase;

/**
 * Bulk quotes, buying: accepting a quote puts a quoted line in the cart (quantity locked, price
 * fixed, "Quote #123"), no shop discount or iruali voucher on it while points still pay for it, the
 * totals add up to the laari, the order item keeps the quote, commission and earnings are on the
 * quoted price, the invoice carries the business details, expiry and stock checks.
 */
class BulkQuoteCheckoutTest extends TestCase
{
    use BuildsQuotes, RefreshDatabase;

    protected User $shopUser;

    protected Product $product;

    protected User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75]);
        $this->shopUser = $this->shop('Island Crafts', 10);
        $this->product = $this->productOf($this->shopUser, 120, ['name' => ['en' => 'Coconut soap'], 'stock_quantity' => 500]);
        $this->buyer = $this->customer(['loyalty_points' => 0]);
    }

    protected function quoted(array $state = []): QuoteRequest
    {
        return $this->quoteFor($this->buyer, $this->product, $state + ['status' => 'quoted', 'unit_price' => 80, 'quoted_quantity' => 30]);
    }

    public function test_accepting_puts_a_quoted_line_in_the_cart_with_a_fixed_price_and_locked_quantity(): void
    {
        $quote = $this->quoted();

        $this->accept($quote)->assertRedirect(route('cart'));

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $line = CartItem::sole();
        $this->assertSame([$this->product->id, 30, '80.00', $quote->id], [$line->product_id, $line->quantity, $line->price, $line->quote_request_id]);
        $this->assertEquals(2400, $this->activeCart($this->buyer)->total);
        Notification::assertSentTo($this->shopUser, QuoteAccepted::class);
        $this->assertSame(500, $this->product->fresh()->stock_quantity, 'a quote does not hold stock');

        $this->get(route('cart'))->assertOk()
            ->assertSee('data-quoted-line="'.$quote->id.'"', false)->assertSee('Quote #'.$quote->id)
            ->assertSee('fixed by the quote')->assertSee('MVR 2,400.00')->assertSee('MVR 80.00')
            ->assertDontSee('name="quantity"', false)->assertDontSee(route('cart.saveForLater', $line), false);

        // The quantity can't be changed, and a later price change doesn't touch it
        $this->put(route('cart.update', $line), ['quantity' => 5])->assertRedirect(route('cart'));
        $this->assertSame(30, $line->fresh()->quantity);
        $this->product->update(['price' => 150]);
        $this->assertEquals(2400, $this->activeCart($this->buyer)->fresh()->total);

        // More of the same product is a line of its own, at the shop's price
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])->assertRedirect(route('cart'));
        $this->assertSame(30, $line->fresh()->quantity);
        $extra = CartItem::whereNull('quote_request_id')->sole();
        $this->assertSame(2, $extra->quantity);
        $this->assertEquals(2700, $this->activeCart($this->buyer)->fresh()->total);

        // It can be accepted only once
        $this->accept($quote)->assertRedirect(route('quotes.show', $quote));
        $this->assertSame(1, CartItem::whereNotNull('quote_request_id')->count());

        $this->get(route('checkout'))->assertOk()->assertSee('Quote #'.$quote->id)->assertSee('quoted price');
    }

    public function test_the_quoted_line_keeps_its_quantity_when_a_guest_cart_is_merged_in(): void
    {
        $quote = $this->quoted();
        $this->accept($quote);
        auth()->logout();

        // As a guest, the same product goes in the cart; signing in merges it as its own line
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 3]);
        $this->post(route('login'), ['email' => $this->buyer->email, 'password' => 'password']);

        $lines = $this->activeCart($this->buyer)->items()->orderBy('id')->get();
        $this->assertSame([[30, $quote->id], [3, null]], $lines->map(fn ($l) => [$l->quantity, $l->quote_request_id])->all());
    }

    public function test_quoted_lines_get_no_multi_buy_and_no_shop_code(): void
    {
        $this->offerFor($this->shopUser, [['min_qty' => 2, 'percent' => 10]], [$this->product]);
        $this->codeFor($this->shopUser, 'REEF10');
        $quote = $this->quoted();
        $this->accept($quote);

        // A shop code with only quoted lines to work on is refused, saying why
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF10'])->assertSessionHasErrors(['shop_code' => 'Shop codes do not apply to items bought on a quote.']);

        // With a normal line of the same product: the deals are worked on that line alone (3 units, not 33)
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 3]);
        $this->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF10'])->assertSessionHasNoErrors();

        $deals = app(ShopDiscountService::class)->forCart($this->activeCart($this->buyer));
        $quotedLine = CartItem::whereNotNull('quote_request_id')->sole();
        $normalLine = CartItem::whereNull('quote_request_id')->sole();
        $this->assertSame(0.0, (float) $deals['lines'][$quotedLine->id]['multibuy']);
        $this->assertSame(0.0, (float) $deals['lines'][$quotedLine->id]['code']);
        $this->assertSame(36.0, (float) $deals['lines'][$normalLine->id]['multibuy']); // 10% of 360
        $this->assertSame(32.4, (float) $deals['lines'][$normalLine->id]['code']); // 10% of 324
        $this->assertNull($deals['lines'][$quotedLine->id]['next_tier']);

        $order = $this->placeOrder($this->buyer);
        $items = $order->items->keyBy(fn ($item) => $item->quote_request_id ? 'quoted' : 'normal');
        $this->assertSame(['80.00', '0.00', '0.00'], [$items['quoted']->price, $items['quoted']->multibuy_discount, $items['quoted']->shop_code_discount]);
        $this->assertSame(['120.00', '36.00', '32.40'], [$items['normal']->price, $items['normal']->multibuy_discount, $items['normal']->shop_code_discount]);
        $this->assertEquals(68.40, $order->shop_discount);
        $this->assertOrderAddsUp($order);
    }

    public function test_the_voucher_works_on_the_rest_of_the_cart_points_pay_for_quotes_and_the_totals_add_up(): void
    {
        $other = $this->shop('Reefline Marine');
        $mask = $this->productOf($other, 99.99, ['name' => ['en' => 'Snorkel mask']]);
        $quote = $this->quoted(['unit_price' => 80.33, 'quoted_quantity' => 30]); // 2,409.90
        Voucher::factory()->create(['code' => 'IRU10', 'type' => 'percent', 'amount' => 10, 'min_order' => 150, 'valid_from' => null, 'valid_until' => null]);
        Voucher::factory()->create(['code' => 'BIG500', 'type' => 'percent', 'amount' => 10, 'min_order' => 500, 'valid_from' => null, 'valid_until' => null]);
        $this->buyer->forceFill(['loyalty_points' => 1000])->save();

        $this->accept($quote);
        $this->post(route('cart.add'), ['product_id' => $mask->id, 'quantity' => 2]); // 199.98

        // The minimum order counts the other items only (199.98), not the 2,609.88 in the cart
        $this->post(route('cart.applyVoucher'), ['voucher_code' => 'BIG500'])->assertSessionHasErrors('voucher_code');
        $this->post(route('cart.applyVoucher'), ['voucher_code' => 'IRU10'])->assertSessionHasNoErrors();
        $this->post(route('checkout.redeemPoints'), ['points' => 2000])->assertSessionHasErrors('points');
        $this->post(route('checkout.redeemPoints'), ['points' => 1000])->assertSessionHasNoErrors();

        $cart = app(CartService::class)->currentCart();
        $summary = app(CartService::class)->getCartSummary($cart);
        $this->assertEquals(2609.88, $summary['subtotal']);
        $this->assertEquals(0, $summary['shop_discount']);
        $this->assertEquals(20.00, $summary['voucher_discount'], '10% of 199.98, nothing off the quote');
        $this->assertEquals(1589.88, $summary['total'], '2,609.88 - 20.00 - 1,000 points (points pay for the quote too)');
        $this->assertEquals(1589.88, app(DiscountService::class)->calculateTotalDiscount($cart)['final_total']);

        $this->get(route('cart'))->assertOk()->assertSee('Voucher')->assertSee('MVR 20.00')->assertSee('No vouchers or shop discounts on quoted prices');

        $order = $this->placeOrder($this->buyer);
        $this->assertEquals(20.00, $order->voucher_discount);
        $this->assertSame(1000, (int) $order->points_redeemed);
        $this->assertEquals(1664.88, $order->total_amount, '1,589.88 + 75 delivery');
        $this->assertSame(15, $order->loyalty_points_earned, 'points are earned on what was paid, quotes included');
        $this->assertOrderAddsUp($order);
        $this->assertSame(1, Voucher::where('code', 'IRU10')->value('used_count'));
    }

    public function test_a_voucher_is_refused_when_everything_in_the_cart_is_quoted(): void
    {
        Voucher::factory()->create(['code' => 'IRU10', 'type' => 'percent', 'amount' => 10, 'valid_from' => null, 'valid_until' => null]);
        $this->accept($this->quoted());

        $this->post(route('cart.applyVoucher'), ['voucher_code' => 'IRU10'])
            ->assertSessionHasErrors(['voucher_code' => 'iruali vouchers do not apply to items bought on a quote.']);

        // One already in the session is dropped with the reason, and nothing comes off
        session(['voucher_code' => 'IRU10']);
        $discounts = app(DiscountService::class)->calculateTotalDiscount($this->activeCart($this->buyer));
        $this->assertSame(0, $discounts['voucher']['amount']);
        $this->assertSame('iruali vouchers do not apply to items bought on a quote.', $discounts['voucher']['error']);
        $this->assertEquals(2400, $discounts['final_total']);
    }

    public function test_the_order_item_keeps_the_quote_and_commission_and_earnings_are_on_the_quoted_price(): void
    {
        $quote = $this->quoted();
        $this->accept($quote);

        $order = $this->placeOrder($this->buyer);

        $item = $order->items->sole();
        $this->assertSame($quote->id, $item->quote_request_id);
        $this->assertTrue($item->quoteRequest->is($quote));
        $this->assertSame(['80.00', 30], [$item->price, $item->quantity]);

        $part = $order->sellerOrders()->sole();
        $this->assertEquals(2400, $part->subtotal);
        $this->assertEquals(240, $part->commission_amount, '10% of the quoted price');
        $this->assertEquals(2160, $part->seller_earnings);
        $this->assertEquals(2475, $order->total_amount);
        $this->assertSame(470, $this->product->fresh()->stock_quantity, 'stock is taken when the order is placed');

        $quote->refresh();
        $this->assertSame('ordered', $quote->status);
        $this->assertSame($order->id, $quote->order_id);
        $this->assertNotNull($quote->ordered_at);
        $this->assertSame(0, CartItem::count(), 'the cart was emptied');

        $this->actingAs($this->buyer)->get(route('quotes.show', $quote))->assertOk()->assertSee('Ordered on')->assertSee($order->order_number);
        $this->actingAs($this->shopUser)->get(route('seller.quotes.show', $quote))->assertOk()->assertSee($order->order_number)->assertSee(route('seller.orders.show', $order), false);
        $this->assertOrderAddsUp($order);
    }

    public function test_the_invoice_carries_the_business_details_of_the_quote(): void
    {
        $quote = $this->quoted();
        $this->accept($quote);

        // The checkout's business box is left off: the quote's details go on the order anyway
        $this->post(route('orders.store'), [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu', 'shipping_zip' => '',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'islands', 'payment_method' => 'bml', 'agree_terms' => '1',
        ])->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertSame(['Sun Island Resort Pvt Ltd', '1012345GST501', 'M. Sunny Building, Malé'], [$order->buyer_business_name, $order->buyer_tin, $order->buyer_business_address]);

        $order = $this->pay($order);
        $part = $order->sellerOrders()->sole();
        $this->assertNotNull($part->invoice_number);
        $this->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('data-buyer-business', false)->assertSee('Sun Island Resort Pvt Ltd')->assertSee('1012345GST501')->assertSee('M. Sunny Building, Malé')
            ->assertSee('MVR 80.00');
        $this->actingAs($this->shopUser)->get(route('seller.orders.invoice', $order))->assertOk()->assertSee('Sun Island Resort Pvt Ltd');
    }

    public function test_business_details_given_at_checkout_win_over_the_quotes(): void
    {
        $this->accept($this->quoted());

        $this->post(route('orders.store'), [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu', 'shipping_zip' => '',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'islands', 'payment_method' => 'bml', 'agree_terms' => '1',
            'business_invoice' => '1', 'buyer_business_name' => 'Sun Island Holdings Pvt Ltd', 'buyer_tin' => '', 'buyer_business_address' => 'H. Holding House, Malé',
        ])->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertSame(['Sun Island Holdings Pvt Ltd', null, 'H. Holding House, Malé'], [$order->buyer_business_name, $order->buyer_tin, $order->buyer_business_address]);
    }

    public function test_an_expired_quote_cannot_be_accepted(): void
    {
        $quote = $this->quoted(['valid_until' => '2026-10-20']);

        $this->travelTo(Carbon::parse('2026-10-20 23:30'));
        $this->actingAs($this->buyer)->get(route('quotes.show', $quote))->assertOk()->assertSee('Accept and add to cart')->assertSee('This price holds until the end of 20 Oct 2026.');

        $this->travelTo(Carbon::parse('2026-10-21 00:05'));
        $this->accept($quote)->assertRedirect(route('quotes.show', $quote));
        $this->assertSame(0, CartItem::count());
        $this->assertSame('expired', $quote->fresh()->status);
        $this->get(route('quotes.show', $quote))->assertOk()->assertSee('This quote expired on 20 Oct 2026.')->assertDontSee('Accept and add to cart')
            ->assertSee('Ask for a new quote');
    }

    public function test_an_expired_quoted_line_is_taken_out_of_the_cart_with_a_notice(): void
    {
        $quote = $this->quoted(['valid_until' => '2026-10-20']);
        $this->accept($quote);
        $this->post(route('cart.add'), ['product_id' => $this->productOf($this->shopUser, 10)->id, 'quantity' => 1]);

        $this->travelTo(Carbon::parse('2026-10-21 09:00'));
        $this->get(route('checkout'))->assertRedirect(route('cart'));
        $this->get(route('cart'))->assertOk()->assertSee('data-quote-notices', false)
            ->assertSee('Quote #'.$quote->id.' for &quot;Coconut soap&quot; expired on 20 Oct 2026, so it was taken out of your cart.', false)
            ->assertDontSee('data-quoted-line', false);
        $this->assertSame(0, CartItem::whereNotNull('quote_request_id')->count());
        $this->assertSame('expired', $quote->fresh()->status);

        // Shown once
        $this->get(route('cart'))->assertOk()->assertDontSee('data-quote-notices', false);
        // The rest of the cart checks out as usual
        $this->get(route('checkout'))->assertOk();
    }

    public function test_placing_the_order_refuses_a_quote_that_expired_on_the_way(): void
    {
        $quote = $this->quoted(['valid_until' => '2026-10-20']);
        $this->accept($quote);

        $this->travelTo(Carbon::parse('2026-10-21 09:00'));
        $result = $this->checkout($this->buyer);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expired on 20 Oct 2026', $result['message']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, CartItem::count(), 'the line is taken out');
        $this->assertSame(500, $this->product->fresh()->stock_quantity);
    }

    public function test_a_quote_closed_while_the_order_is_being_placed_stops_the_order(): void
    {
        $quote = $this->quoted();
        $this->accept($quote);
        $cart = $this->activeCart($this->buyer)->load('items');

        // Closed by staff after the cart was checked, before the order's transaction reached the quote
        $quote->forceFill(['status' => 'declined', 'declined_by' => 'admin'])->save();

        try {
            $this->quotes()->placeOrder(Order::factory()->create(['user_id' => $this->buyer->id]), $cart);
            $this->fail('the order should have been refused');
        } catch (\App\Exceptions\QuoteException $e) {
            $this->assertSame('Quote #'.$quote->id.' for "Coconut soap" is declined, so it was taken out of your cart.', $e->getMessage());
        }
        $this->assertSame('declined', $quote->fresh()->status);
        $this->assertNull($quote->fresh()->order_id);
    }

    public function test_stock_is_checked_when_accepting_and_at_checkout(): void
    {
        $this->product->update(['stock_quantity' => 20]);
        $quote = $this->quoted(); // 30

        $this->accept($quote)->assertRedirect(route('quotes.show', $quote));
        $this->assertStringContainsString('Not enough stock: Island Crafts has 20 of "Coconut soap" right now and the quote is for 30.', session('notification')['message']);
        $this->assertSame('quoted', $quote->fresh()->status);
        $this->assertSame(0, CartItem::count());

        $this->product->update(['stock_quantity' => 40]);
        $this->accept($quote)->assertRedirect(route('cart'));

        // The cart's own check counts the quoted units
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 15]);
        $this->assertSame(0, CartItem::whereNull('quote_request_id')->count(), 'only 10 more fit');

        // Someone else bought some meanwhile: checkout says so before anything is written
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 5]);
        $this->product->update(['stock_quantity' => 32]);
        $result = $this->checkout($this->buyer);
        $this->assertFalse($result['success']);
        $this->assertSame('Not enough stock for "Coconut soap": 32 left, and your cart has 35 (your quote and any other units). Please change your cart, or ask the shop.', $result['message']);
        $this->assertSame(0, Order::count());
        $this->assertSame(2, CartItem::count(), 'nothing is taken out for stock');
    }

    public function test_a_quote_cannot_be_accepted_while_the_product_is_off_sale_or_the_shop_is_away(): void
    {
        $quote = $this->quoted();

        $this->product->update(['is_active' => false]);
        $this->accept($quote)->assertRedirect(route('quotes.show', $quote));
        $this->assertSame('"Coconut soap" is no longer on sale, so this quote can\'t be used.', session('notification')['message']);

        $this->product->update(['is_active' => true]);
        $this->shopUser->forceFill(['holiday_mode' => true, 'holiday_until' => today()->addDays(3)])->save();
        $this->accept($quote)->assertRedirect(route('quotes.show', $quote));
        $this->assertStringContainsString('Island Crafts is on holiday', session('notification')['message']);

        $this->assertSame('quoted', $quote->fresh()->status);
        $this->assertSame(0, CartItem::count());
        Notification::assertNotSentTo($this->shopUser, QuoteAccepted::class);
    }

    public function test_options_are_checked_on_the_quoted_option(): void
    {
        $hoodie = $this->productOf($this->shopUser, 300, ['name' => ['en' => 'Staff hoodie'], 'has_variants' => true]);
        $medium = \App\Models\ProductVariant::factory()->for($hoodie)->attributes(['Size' => 'M'])->stock(50)->create();
        $hoodie->syncStockFromVariants();
        $quote = $this->quoteFor($this->buyer, $hoodie, ['status' => 'quoted', 'product_variant_id' => $medium->id, 'variant_name' => 'M', 'unit_price' => 250, 'quoted_quantity' => 40]);

        $medium->update(['is_active' => false]);
        $this->accept($quote)->assertRedirect(route('quotes.show', $quote));
        $this->assertSame(0, CartItem::count());

        $medium->update(['is_active' => true]);
        $this->accept($quote)->assertRedirect(route('cart'));
        $line = CartItem::sole();
        $this->assertSame([$medium->id, 40, '250.00'], [$line->product_variant_id, $line->quantity, $line->price]);

        $order = $this->placeOrder($this->buyer);
        $this->assertSame(10, $medium->fresh()->stock_quantity, 'taken from the option');
        $this->assertSame('M', $order->items->sole()->variant_name);
    }

    public function test_the_customer_declines_or_withdraws_and_the_shop_is_told(): void
    {
        $new = $this->quoteFor($this->buyer, $this->product);
        $this->actingAs($this->buyer)->get(route('quotes.show', $new))->assertSee('Withdraw this request');
        $this->post(route('quotes.decline', $new), ['reason' => ''])->assertRedirect(route('quotes.show', $new));
        $this->assertSame(['declined', 'customer'], [$new->fresh()->status, $new->fresh()->declined_by]);
        Notification::assertSentTo($this->shopUser, QuoteWithdrawn::class, fn ($n) => $n->quote->is($new));
        $this->assertSame('Sun Island Resort Pvt Ltd no longer needs a quote for Coconut soap.', (new QuoteWithdrawn($new->fresh()))->toMail($this->shopUser)->introLines[0]);

        // An accepted quote comes out of the cart when declined
        $accepted = $this->quoteFor($this->buyer, $this->productOf($this->shopUser, 50), ['status' => 'quoted']);
        $this->accept($accepted);
        $this->assertSame(1, CartItem::count());
        $this->post(route('quotes.decline', $accepted), ['reason' => 'We found it cheaper.'])->assertRedirect(route('quotes.show', $accepted));
        $this->assertSame('declined', $accepted->fresh()->status);
        $this->assertSame('We found it cheaper.', $accepted->fresh()->decline_reason);
        $this->assertSame(0, CartItem::count());

        // An ordered quote can't be declined
        $ordered = $this->quoteFor($this->buyer, $this->productOf($this->shopUser, 50), ['status' => 'ordered']);
        $this->post(route('quotes.decline', $ordered))->assertRedirect(route('quotes.show', $ordered));
        $this->assertSame('ordered', $ordered->fresh()->status);
    }

    public function test_a_removed_quoted_line_can_go_back_in_the_cart_while_the_quote_holds(): void
    {
        $quote = $this->quoted();
        $this->accept($quote);
        $this->delete(route('cart.remove', CartItem::sole()))->assertRedirect(route('cart'));
        $this->assertSame(0, CartItem::count());
        $this->assertSame('accepted', $quote->fresh()->status);

        $this->get(route('quotes.show', $quote))->assertOk()->assertSee('Accepted, but it is not in your cart right now.')->assertSee('Add to cart again');
        $this->post(route('quotes.cart', $quote))->assertRedirect(route('cart'));
        $this->assertSame([30, '80.00'], [CartItem::sole()->quantity, CartItem::sole()->price]);

        $this->post(route('quotes.cart', $quote))->assertRedirect(route('quotes.show', $quote));
        $this->assertSame(1, CartItem::count(), 'already there');
        $this->get(route('quotes.show', $quote))->assertSee('Go to cart');
    }

    public function test_an_unpaid_order_that_is_cancelled_gives_the_quote_back(): void
    {
        $quote = $this->quoted();
        $this->accept($quote);
        $order = $this->placeOrder($this->buyer);
        $this->assertSame('ordered', $quote->fresh()->status);

        $this->actingAs($this->buyer)->post(route('orders.cancel', $order))->assertRedirect(route('orders.show', $order));
        $this->assertSame('cancelled', $order->fresh()->status);
        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertNull($quote->order_id);
        $this->post(route('quotes.cart', $quote))->assertRedirect(route('cart'));
        $this->assertSame(1, CartItem::whereNotNull('quote_request_id')->count());

        // A paid order that is cancelled keeps its quote used
        $order = $this->pay($this->placeOrder($this->buyer));
        app(\App\Services\OrderService::class)->updateOrderStatus($order, 'cancelled');
        $this->assertSame('ordered', $quote->fresh()->status);
    }

    public function test_another_customer_gets_403_everywhere(): void
    {
        $quote = $this->quoted();
        $stranger = $this->customer();

        $this->actingAs($stranger)->get(route('quotes.show', $quote))->assertForbidden();
        $this->post(route('quotes.accept', $quote))->assertForbidden();
        $this->post(route('quotes.cart', $quote))->assertForbidden();
        $this->post(route('quotes.decline', $quote))->assertForbidden();
        $this->post(route('quotes.messages', $quote), ['body' => 'Hi'])->assertForbidden();
        $this->get(route('quotes.index'))->assertOk()->assertDontSee('data-quote-row="'.$quote->id.'"', false);
        $this->assertSame('quoted', $quote->fresh()->status);
        $this->assertSame(0, CartItem::count());

        // A shop is a stranger to the customer's side too
        $this->actingAs($this->shopUser)->get(route('quotes.show', $quote))->assertForbidden();
        $this->post(route('quotes.accept', $quote))->assertForbidden();

        auth()->logout();
        $this->get(route('quotes.show', $quote))->assertRedirect(route('login'));
        $this->post(route('quotes.accept', $quote))->assertRedirect(route('login'));
    }

    public function test_the_hourly_command_marks_quotes_past_their_day_expired(): void
    {
        $quoted = $this->quoted(['valid_until' => '2026-10-14']);
        $accepted = $this->quoteFor($this->buyer, $this->productOf($this->shopUser, 50), ['status' => 'accepted', 'valid_until' => '2026-10-14']);
        $current = $this->quoteFor($this->buyer, $this->productOf($this->shopUser, 50), ['status' => 'quoted', 'valid_until' => '2026-10-15']);
        $new = $this->quoteFor($this->buyer, $this->productOf($this->shopUser, 50));

        $this->artisan('quotes:expire')->expectsOutput('Expired 2 quote(s).')->assertSuccessful();

        $this->assertSame(['expired', 'expired', 'quoted', 'new'], [$quoted->fresh()->status, $accepted->fresh()->status, $current->fresh()->status, $new->fresh()->status]);
        $this->assertNotNull($quoted->fresh()->expired_at);

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($event) => $event->command);
        $this->assertTrue($events->contains(fn ($command) => str_contains((string) $command, 'quotes:expire')));
    }
}

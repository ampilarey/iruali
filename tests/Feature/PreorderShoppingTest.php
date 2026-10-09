<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\MultiBuyOffer;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\SellerDeliverySetting;
use App\Models\Setting;
use App\Models\ShopDiscountCode;
use App\Models\ShopTaxProfile;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PreorderService;
use App\Services\ShopDiscountService;
use App\Support\FeedToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders from the shopper's side: what the product page, cart and checkout say, paying in full,
 * the limit (across variants), holiday shops, mixed carts, feeds and structured data.
 */
class PreorderShoppingTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75, 'delivery_fee_greater_male' => 30]);
    }

    /**
     * The JSON-LD blocks on a page, decoded.
     *
     * @return list<array<string, mixed>>
     */
    protected function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $m);

        return array_map(fn ($json) => json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5), true), $m[1]);
    }

    public function test_the_product_page_says_when_a_pre_order_ships_and_the_button_says_pre_order(): void
    {
        $shop = $this->seller();
        SellerDeliverySetting::create(['seller_id' => $shop->id, 'ships_within_days' => 2]);
        $product = $this->preorderProduct($shop);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('data-preorder-notice', false)
            ->assertSee('Pre-order: ships around 3 Nov')
            ->assertSee('Arrives on the next ship from Colombo')
            ->assertSee('Available to pre-order')
            ->assertSee('data-text-preorder="Pre-order">Pre-order</span>', false)
            ->assertSee('id="buy-form"', false)
            // Wave 1's delivery box: the expected date, the shop's 2 dispatch days, then 1–2 days to Greater Malé
            ->assertSee('Arrives around 6–7 Nov')
            // Back-in-stock alerts are still offered
            ->assertSee('data-stock-alert', false);

        // With stock it is an ordinary product again
        $product->update(['stock_quantity' => 3]);
        $this->get(route('products.show', $product))->assertOk()
            ->assertDontSee('data-preorder-notice', false)
            ->assertSee('data-text-preorder="Pre-order">Add to cart</span>', false)
            ->assertDontSee('Arrives around');
    }

    public function test_no_pre_order_when_switched_off_past_its_date_or_with_no_units_left(): void
    {
        $shop = $this->seller();
        $product = $this->preorderProduct($shop, ['preorder_limit' => 2]);
        $this->get(route('products.show', $product))->assertSee('data-preorder-notice', false);

        // Every unit taken
        $this->paidOrder(User::factory()->create(), [[$product, 2]]);
        $this->get(route('products.show', $product))->assertOk()->assertDontSee('data-preorder-notice', false)->assertDontSee('id="buy-form"', false);
        $this->assertSame(0, app(PreorderService::class)->unitsLeft($product->fresh()));

        // Switched off, or past the expected date
        $other = $this->preorderProduct($shop, ['preorder_enabled' => false]);
        $this->get(route('products.show', $other))->assertDontSee('data-preorder-notice', false);
        $late = $this->preorderProduct($shop, ['preorder_ship_date' => '2026-10-19']);
        $this->get(route('products.show', $late))->assertDontSee('data-preorder-notice', false);
        $this->travelTo(Carbon::parse('2026-11-04 09:00'));
        $this->assertFalse(app(PreorderService::class)->accepting($other->fresh()->forceFill(['preorder_enabled' => true])));
    }

    public function test_the_cart_and_checkout_mark_pre_order_lines_with_the_date(): void
    {
        $shop = $this->seller();
        SellerDeliverySetting::create(['seller_id' => $shop->id, 'ships_within_days' => 1]);
        $product = $this->preorderProduct($shop);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect(route('cart'));
        $this->assertSame(2, (int) CartItem::sum('quantity'));

        // The limit caps what goes in the cart (5 units, 2 already in it)
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 4]);
        $this->assertSame(2, (int) CartItem::sum('quantity'));
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 3]);
        $this->assertSame(5, (int) CartItem::sum('quantity'));

        $this->get(route('cart'))->assertOk()
            ->assertSee('data-preorder-line', false)
            ->assertSee('Pre-order · ships around 3 Nov')
            ->assertSee('Arrives on the next ship from Colombo');

        $this->enableBml();
        $this->get(route('checkout'))->assertOk()
            ->assertSee('data-checkout-preorders', false)
            ->assertSee('Your order has 1 pre-order item')
            ->assertSee('Pre-order · ships around 3 Nov')
            // The shop's estimate counts from the expected date: 3 Nov + 1 dispatch day + 2–5 days to other islands
            ->assertSee('Arrives around 6–9 Nov');
        $this->assertSame([CartItem::sole()->id => '2026-11-03'], session(PreorderService::SESSION_SHOWN));
    }

    public function test_the_customer_pays_in_full_at_checkout_and_no_stock_is_taken(): void
    {
        $this->enableBml();
        $shop = $this->seller();
        $product = $this->preorderProduct($shop, ['price' => 250]);
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$product, 2]]);

        $this->actingAs($customer)->get(route('checkout'))->assertOk();
        $this->post(route('orders.store'), $this->checkoutFields())->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertEquals(575, $order->total_amount, '2 × 250 and 75 delivery: paid in full now');
        $this->assertSame(57500, PaymentTransaction::sole()->amount, 'BML is asked for the whole total');

        $item = $order->items()->sole();
        $this->assertTrue($item->is_preorder);
        $this->assertSame('2026-11-03', $item->preorder_ship_date->toDateString());
        $this->assertSame(2, $item->preorderWaitingQuantity());
        $part = $order->sellerOrders()->sole();
        $this->assertTrue($part->awaiting_stock);
        $this->assertSame('2026-11-03', $part->preorder_ship_date->toDateString());

        $this->assertSame(0, $product->fresh()->stock_quantity, 'nothing is taken from stock for a pre-order');
        $this->assertSame(3, app(PreorderService::class)->unitsLeft($product->fresh()));
    }

    public function test_the_limit_holds_across_orders_and_frees_up_when_an_order_is_cancelled(): void
    {
        $shop = $this->seller();
        $product = $this->preorderProduct($shop, ['preorder_limit' => 5]);
        $first = $this->paidOrder(User::factory()->create(), [[$product, 3]]);

        // The cart's own check counts the pre-order units left (checked again under the product's lock)
        $refused = $this->order(User::factory()->create(), [[$product, 3]]);
        $this->assertFalse($refused['success']);
        $this->assertStringContainsString('Available: 2, Requested: 3', $refused['message']);

        $this->paidOrder(User::factory()->create(), [[$product, 2]]);
        $full = $this->order(User::factory()->create(), [[$product, 1]]);
        $this->assertFalse($full['success']);
        $this->assertStringContainsString('Available: 0, Requested: 1', $full['message']);
        $this->assertSame(2, Order::count());

        // A cancelled pre-order gives its units back to the limit, and never to the shelf
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($first, 'cancelled'));
        $this->assertSame(3, app(PreorderService::class)->unitsLeft($product->fresh()));
        $this->assertSame(0, $product->fresh()->stock_quantity, 'units that never arrived are not put back');
        $this->assertTrue($this->order(User::factory()->create(), [[$product, 3]])['success']);
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $shop = $this->seller();
        $product = $this->preorderProduct($shop, ['preorder_limit' => 10]);
        // An ordinary product with 1 left and a quantity of 2 is refused as before
        $scarce = $this->stockedProduct($shop, 40, 1);
        $this->assertFalse($this->order(User::factory()->create(), [[$scarce, 2]])['success']);

        foreach ([3, 4, 2] as $quantity) {
            $this->paidOrder(User::factory()->create(), [[$product, $quantity]]);
        }

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, $scarce->fresh()->stock_quantity);
        $this->assertSame(0, Product::where('stock_quantity', '<', 0)->count());
        $this->assertSame(9, app(PreorderService::class)->waitingUnits($product->id));
    }

    public function test_a_shop_on_holiday_takes_no_pre_orders(): void
    {
        $shop = $this->seller();
        $product = $this->preorderProduct($shop);
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$product, 1]]);
        $shop->forceFill(['holiday_mode' => true, 'holiday_until' => '2026-10-30', 'holiday_started_at' => now()])->save();

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('data-shop-holiday', false)
            ->assertDontSee('data-preorder-notice', false)
            ->assertDontSee('id="buy-form"', false);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHas('notification', fn (array $flash) => str_contains($flash['message'], 'is on holiday'));
        $this->assertSame(1, (int) CartItem::sum('quantity'), 'nothing more is added');

        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('is on holiday', $result['message']);
        $this->assertSame(0, Order::count());
        $this->assertFalse(app(PreorderService::class)->isPreorderable($product->fresh()));
    }

    public function test_a_sold_out_option_can_be_pre_ordered_and_the_limit_counts_across_options(): void
    {
        $shop = $this->seller();
        [$product, $small, $medium, $large] = $this->preorderVariantProduct($shop, 4);
        $medium->update(['stock_quantity' => 0]); // M and L sold out, S in stock

        $page = $this->get(route('products.show', $product))->assertOk()->getContent();
        $this->assertStringContainsString('Pre-order: ships around 3 Nov', $page);
        preg_match('/data-variants="([^"]+)"/', $page, $m);
        $variants = collect(json_decode(html_entity_decode($m[1]), true))->keyBy('id');
        $this->assertSame(0, $variants[$small->id]['preorder'], 'in stock: an ordinary option');
        $this->assertSame(4, $variants[$medium->id]['preorder']);
        $this->assertSame(4, $variants[$large->id]['preorder']);

        // M × 3 and L × 2 fit one at a time, but not together: the limit is the product's
        $refusedTogether = $this->order(User::factory()->create(), [[$product, 3, $medium], [$product, 2, $large]]);
        $this->assertFalse($refusedTogether['success']);
        $this->assertSame('Sorry, only 4 pre-order units of "'.$product->name.'" are left.', $refusedTogether['message']);
        $this->assertSame(0, Order::count());

        // M × 3 as a pre-order and S × 1 from stock in one order
        $order = $this->paidOrder(User::factory()->create(), [[$product, 3, $medium], [$product, 1, $small]]);
        $lines = $order->items()->get()->keyBy('product_variant_id');
        $this->assertTrue($lines[$medium->id]->is_preorder);
        $this->assertFalse($lines[$small->id]->is_preorder);
        $this->assertSame(4, $small->fresh()->stock_quantity);
        $this->assertSame(0, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $product->fresh()->stock_quantity, 'the product total still follows its variants');

        // One unit left for the whole product: L × 2 is refused, L × 1 is taken
        $refused = $this->order(User::factory()->create(), [[$product, 2, $large]]);
        $this->assertFalse($refused['success']);
        $this->assertStringContainsString('Available: 1, Requested: 2', $refused['message']);
        $this->paidOrder(User::factory()->create(), [[$product, 1, $large]]);
        $this->assertSame(0, app(PreorderService::class)->unitsLeft($product->fresh()));

        // An option still in stock sells as usual; a switched-off option takes no pre-orders
        $this->assertTrue($this->order(User::factory()->create(), [[$product, 2, $small]])['success']);
        $large->update(['is_active' => false]);
        $this->assertSame(0, app(PreorderService::class)->unitsLeft($product->fresh(), $large->fresh()));
    }

    public function test_mixed_carts_add_up_with_shop_discounts_and_gst(): void
    {
        $this->enableBml();
        Setting::set(['gst_rate' => 8, 'default_commission_rate' => 10]);
        $reef = $this->seller('Reef Goods');
        ShopTaxProfile::create(['user_id' => $reef->id, 'gst_registered' => true, 'tin' => '1012345GST501', 'registered_name' => 'Reef Goods Pvt Ltd', 'business_address' => 'Malé']);
        $island = $this->seller('Island Crafts');

        $preorder = $this->preorderProduct($reef, ['price' => 540]);
        $inStock = $this->stockedProduct($reef, 540, 10);
        $other = $this->stockedProduct($island, 200, 10);
        // Reef Goods: 10% off with its code; Island Crafts: buy 2, save 5%
        $code = new ShopDiscountCode(['code' => 'TENOFF', 'type' => 'percent', 'value' => 10, 'applies_to' => 'all', 'is_active' => true]);
        $code->seller_id = $reef->id;
        $code->save();
        session([ShopDiscountService::SESSION_KEY => [$reef->id => 'TENOFF']]);
        $offer = new MultiBuyOffer(['tiers' => [['min_qty' => 2, 'percent' => 5]]]);
        $offer->seller_id = $island->id;
        $offer->save();
        $other->forceFill(['multibuy_offer_id' => $offer->id])->save();

        $customer = User::factory()->create();
        $order = $this->paidOrder($customer, [[$preorder, 1], [$inStock, 1], [$other->fresh(), 2]]);

        // Goods 540 + 540 + 400 = 1480; Reef's code takes 108, the multi-buy 20; delivery 75
        $this->assertEquals(128, $order->shop_discount);
        $this->assertEquals(1480 - 128 + 75, $order->total_amount);

        $parts = $order->sellerOrders()->get()->keyBy('seller_id');
        $reefPart = $parts[$reef->id];
        $this->assertEquals(1080, $reefPart->subtotal);
        $this->assertEquals(108, $reefPart->shop_discount);
        $this->assertEquals(972, $reefPart->gst_taxable, 'GST on the pre-order and the in-stock item alike, after the shop code');
        $this->assertEquals(72, $reefPart->gst_amount, '972 × 8/108');
        $this->assertEquals(97.2, $reefPart->commission_amount);
        $this->assertTrue($reefPart->awaiting_stock, 'the shop sends both together once the pre-order stock is in');
        $this->assertNotNull($reefPart->invoice_number, 'paid in full: the invoice is issued now');

        $islandPart = $parts[$island->id];
        $this->assertEquals(400, $islandPart->subtotal);
        $this->assertEquals(20, $islandPart->shop_discount);
        $this->assertFalse($islandPart->awaiting_stock, 'the other shop is not held up');
        $fulfilment = app(\App\Services\FulfilmentService::class);
        $this->assertTrue($fulfilment->advance($islandPart->fresh(), 'processing'));
        $this->assertTrue($fulfilment->advance($islandPart->fresh(), 'shipped'));
        $this->assertTrue($fulfilment->advance($reefPart->fresh(), 'processing'));
        $this->assertFalse($fulfilment->advance($reefPart->fresh(), 'shipped'));

        // Every shop's net goods plus delivery is what the customer paid
        $this->assertEquals((float) $order->total_amount, round($parts->sum(fn ($p) => $p->netSubtotal()) + (float) $order->shipping_amount, 2));
        $this->assertSame(0, $preorder->fresh()->stock_quantity);
        $this->assertSame(9, $inStock->fresh()->stock_quantity);
        $this->assertSame(8, $other->fresh()->stock_quantity);
    }

    public function test_feeds_and_structured_data_say_pre_order_with_the_date(): void
    {
        $shop = $this->seller();
        $preorder = $this->preorderProduct($shop, ['name' => 'Coral lamp']);
        $inStock = $this->stockedProduct($shop, 80, 4);
        [$shirt, $small, , $large] = $this->preorderVariantProduct($shop);

        $xml = $this->get(FeedToken::url('feeds.google-merchant'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');
        $item = fn (string $id) => $xpath->query('//item[g:id="'.$id.'"]')->item(0);

        $this->assertSame('preorder', $xpath->query('g:availability', $item('P'.$preorder->id))->item(0)->nodeValue);
        $this->assertSame('2026-11-03T00:00+0500', $xpath->query('g:availability_date', $item('P'.$preorder->id))->item(0)->nodeValue);
        $this->assertSame('in_stock', $xpath->query('g:availability', $item('P'.$inStock->id))->item(0)->nodeValue);
        $this->assertSame(0, $xpath->query('g:availability_date', $item('P'.$inStock->id))->length);
        $this->assertSame('in_stock', $xpath->query('g:availability', $item('P'.$shirt->id.'-V'.$small->id))->item(0)->nodeValue);
        $this->assertSame('preorder', $xpath->query('g:availability', $item('P'.$shirt->id.'-V'.$large->id))->item(0)->nodeValue);

        // Meta's catalogue only takes "in stock" / "out of stock": an open pre-order can be bought now
        $csv = $this->get(FeedToken::url('feeds.facebook-catalog'))->assertOk()->getContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));
        $byId = collect(array_slice($rows, 1))->keyBy(0);
        $this->assertSame('in stock', $byId['P'.$preorder->id][3]);

        [$productLd] = $this->jsonLd($this->get(route('products.show', $preorder))->assertOk()->getContent());
        $this->assertSame('https://schema.org/PreOrder', $productLd['offers']['availability']);
        $this->assertSame('2026-11-03', $productLd['offers']['availabilityStarts']);
        [$stockLd] = $this->jsonLd($this->get(route('products.show', $inStock))->getContent());
        $this->assertSame('https://schema.org/InStock', $stockLd['offers']['availability']);

        // A shop on holiday shows out of stock, pre-order or not
        $shop->forceFill(['holiday_mode' => true, 'holiday_until' => null, 'holiday_started_at' => now()])->save();
        [$holidayLd] = $this->jsonLd($this->get(route('products.show', $preorder))->getContent());
        $this->assertSame('https://schema.org/OutOfStock', $holidayLd['offers']['availability']);
        \Illuminate\Support\Facades\Cache::flush();
        $this->get(FeedToken::url('feeds.google-merchant'))->assertOk()
            ->assertDontSee('<g:availability>preorder</g:availability>', false)
            ->assertDontSee('availability_date', false);
    }

    public function test_the_smoke_customer_never_places_a_pre_order(): void
    {
        $shop = $this->seller();
        $product = $this->preorderProduct($shop);
        $smoke = User::factory()->create(['is_smoke_test' => true]);

        $result = $this->order($smoke, [[$product, 1]]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('just sold out', $result['message']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_the_smoke_test_still_orders_the_cheapest_product_in_stock(): void
    {
        $html = '<!DOCTYPE html><html><form><input type="hidden" name="_token" value="t" autocomplete="off"></form> agree_terms</html>';
        $site = (new \Tests\Support\FakeSmokeFetcher([], 'http://smoke.test'))
            ->on('GET /', 200, $html)
            ->on('GET /sitemap.xml', 200, '<urlset><url><loc>http://smoke.test/products/p</loc></url><url><loc>http://smoke.test/categories/c</loc></url></urlset>')
            ->on('GET /robots.txt', 200, 'User-agent: *')
            ->on('GET /products/p', 200, $html)->on('GET /categories/c', 200, $html)->on('GET /search', 200, $html)
            ->on('GET /cart', 200, $html)->on('GET /login', 200, $html)->on('GET /up', 200, 'ok')
            ->on('GET /api/health', 200, '{"status":"healthy","commit":"abc1234"}')
            ->on('POST /login', 302, '', ['Location' => 'http://smoke.test/'])
            ->on('POST /cart/add', 302, '', ['Location' => 'http://smoke.test/'])
            ->on('GET /checkout', 200, $html)
            ->on('POST /orders', 302, '', ['Location' => 'https://pay.bml.test/t1'])
            ->on('GET https://pay.bml.test/t1', 200, 'BML')
            ->on('GET /orders', 200, '<a href="http://smoke.test/orders/7">o</a>')
            ->on('POST /orders/7/cancel', 302, '', ['Location' => 'http://smoke.test/orders/7'])
            ->on('GET /orders/7', 200, 'Cancelled');
        $this->app->instance(\App\Support\SmokeFetcher::class, $site);
        config(['services.smoke.email' => 'smoke@iruali.test', 'services.smoke.password' => 'pw']);
        $this->artisan('iruali:smoke --setup')->assertExitCode(0);

        $shop = $this->seller();
        $this->preorderProduct($shop, ['price' => 5]); // cheaper, but out of stock (on pre-order)
        $inStock = $this->stockedProduct($shop, 12, 3);

        $this->artisan('iruali:smoke --base-url=http://smoke.test --place-order')->expectsOutputToContain('SMOKE OK')->assertExitCode(0);
        $this->assertSame($inStock->id, $site->sent('POST', '/cart/add')['data']['product_id']);
    }

    public function test_a_line_that_sold_out_after_the_checkout_page_is_not_turned_into_a_pre_order_silently(): void
    {
        $this->enableBml();
        $shop = $this->seller();
        $product = $this->preorderProduct($shop, ['stock_quantity' => 1]);
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$product, 1]]);

        // The checkout page shows the line in stock; then someone else buys the last one
        $this->actingAs($customer)->get(route('checkout'))->assertOk()->assertDontSee('data-checkout-preorders', false);
        $product->update(['stock_quantity' => 0]);

        $this->post(route('orders.store'), $this->checkoutFields())->assertRedirect(route('cart'))
            ->assertSessionHas('notification');
        $this->assertStringContainsString('is now a pre-order that ships around 3 Nov', session('notification')['message']);
        $this->assertSame(0, Order::count());

        // Seen as a pre-order, it goes through
        $this->get(route('checkout'))->assertOk()->assertSee('data-checkout-preorders', false);
        $this->post(route('orders.store'), $this->checkoutFields())->assertRedirect('https://pay.bml.test/txn_test');
        $this->assertTrue(Order::sole()->items()->sole()->is_preorder);
    }
}

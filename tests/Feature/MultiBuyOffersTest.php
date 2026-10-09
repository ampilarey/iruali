<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\MultiBuyOffer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\ShopDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsShopDeals;
use Tests\TestCase;

/**
 * Multi-buy offers ("Buy 2, save 5%"), set on the shop's product form, for one product or a
 * mix-and-match group; the cart applies them by itself and the shop pays, like a discount code.
 */
class MultiBuyOffersTest extends TestCase
{
    use BuildsShopDeals, RefreshDatabase;

    protected User $customer;

    protected User $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
        Setting::set(['default_commission_rate' => 10]);

        $this->customer = User::factory()->create();
        $this->shop = $this->shop('Island Crafts');
    }

    /**
     * The product form's fields, with the multi-buy fieldset.
     */
    protected function productForm(Product $product, array $multibuy): array
    {
        return [
            'name_en' => $product->getTranslation('name', 'en', false) ?: 'Tee', 'sku' => $product->sku, 'category_id' => $product->category_id,
            'price' => $product->price, 'stock_quantity' => $product->stock_quantity, 'reorder_point' => 5,
            'multibuy' => $multibuy,
        ];
    }

    protected function cartDeals(): array
    {
        return app(ShopDiscountService::class)->forCart(app(CartService::class)->currentCart());
    }

    // ---- Product form and page ---------------------------------------------------------------------

    public function test_the_shop_sets_tiers_on_the_product_form_and_the_product_page_shows_them(): void
    {
        $product = $this->productOf($this->shop, 50, ['category_id' => Category::factory()->create()->id]);

        $this->actingAs($this->shop)->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('Multi-buy offer')->assertSee('name="multibuy[tiers][0][min_qty]"', false);

        $this->put(route('seller.products.update', $product), $this->productForm($product, [
            'tiers' => [['min_qty' => '3', 'percent' => '10'], ['min_qty' => '2', 'percent' => '5'], ['min_qty' => '', 'percent' => '']],
            'group' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));

        $offer = $product->fresh()->multibuyOffer;
        $this->assertNotNull($offer);
        $this->assertFalse($offer->isShared());
        $this->assertSame($this->shop->id, $offer->seller_id);
        $this->assertSame([['min_qty' => 2, 'percent' => 5.0], ['min_qty' => 3, 'percent' => 10.0]], $offer->tierList(), 'smallest quantity first');

        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="10"', false);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Multi-buy offer')->assertSee('Buy 2, save 5%')->assertSee('Buy 3, save 10%');

        // Emptying the tiers removes the offer
        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '', 'percent' => '']], 'group' => '']))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->multibuy_offer_id);
        $this->assertSame(0, MultiBuyOffer::count(), 'an offer no product uses goes');
        $this->get(route('products.show', $product))->assertOk()->assertDontSee('Multi-buy offer');
    }

    public function test_the_product_form_checks_the_tiers_and_groups(): void
    {
        $product = $this->productOf($this->shop, 50, ['category_id' => Category::factory()->create()->id]);
        $rival = $this->shop('Reefline Marine');
        $theirs = $this->offerFor($rival, [['min_qty' => 2, 'percent' => 5]], [$this->productOf($rival, 20)], 'Their tees');
        $this->actingAs($this->shop);

        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '2', 'percent' => '']], 'group' => '']))
            ->assertSessionHasErrors('multibuy.tiers');
        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '2', 'percent' => '10'], ['min_qty' => '3', 'percent' => '5']], 'group' => '']))
            ->assertSessionHasErrors('multibuy.tiers');
        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '1', 'percent' => '10']], 'group' => '']))
            ->assertSessionHasErrors('multibuy.tiers.0.min_qty');
        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '2', 'percent' => '95']], 'group' => '']))
            ->assertSessionHasErrors('multibuy.tiers.0.percent');
        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '2', 'percent' => '5']], 'group' => 'new', 'group_name' => '']))
            ->assertSessionHasErrors('multibuy.group_name');
        $this->put(route('seller.products.update', $product), $this->productForm($product, ['tiers' => [['min_qty' => '2', 'percent' => '5']], 'group' => (string) $theirs->id]))
            ->assertSessionHasErrors('multibuy.group');

        $this->assertNull($product->fresh()->multibuy_offer_id);
        $this->assertSame([$rival->products()->sole()->id], $theirs->products()->pluck('id')->all(), 'nothing joined another shop\'s group');
    }

    public function test_products_join_a_mix_and_match_group_from_the_form(): void
    {
        $category = Category::factory()->create()->id;
        $tee = $this->productOf($this->shop, 25, ['category_id' => $category]);
        $polo = $this->productOf($this->shop, 35, ['category_id' => $category]);
        $this->actingAs($this->shop);

        $this->put(route('seller.products.update', $tee), $this->productForm($tee, ['tiers' => [['min_qty' => '2', 'percent' => '10']], 'group' => 'new', 'group_name' => 'Tees']))->assertSessionHasNoErrors();
        $group = MultiBuyOffer::sole();
        $this->assertSame('Tees', $group->name);

        $this->get(route('seller.products.edit', $polo))->assertOk()->assertSee('Tees (1 product)');
        $this->put(route('seller.products.update', $polo), $this->productForm($polo, ['tiers' => [['min_qty' => '', 'percent' => '']], 'group' => (string) $group->id]))->assertSessionHasNoErrors();
        $this->assertSame($group->id, $polo->fresh()->multibuy_offer_id);
        $this->assertSame([['min_qty' => 2, 'percent' => 10.0]], $group->fresh()->tierList(), 'left empty, the group keeps its tiers');

        // A second group of the same name is refused
        $this->put(route('seller.products.update', $polo), $this->productForm($polo, ['tiers' => [['min_qty' => '2', 'percent' => '5']], 'group' => 'new', 'group_name' => 'tees']))
            ->assertSessionHasErrors('multibuy.group_name');

        $this->get(route('products.show', $tee))->assertOk()->assertSee('Buy 2, save 10%')->assertSee('Mix and match')->assertSee($polo->name);
    }

    // ---- Cart -------------------------------------------------------------------------------------

    public function test_the_cart_applies_the_best_tier_reached_and_shows_the_saving_per_line(): void
    {
        $product = $this->productOf($this->shop, 50);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 5], ['min_qty' => 3, 'percent' => 10]], [$product]);
        $cart = $this->cartFor($this->customer, [[$product, 1]]);
        $this->actingAs($this->customer);

        $line = $this->cartDeals()['lines'][$cart->items()->sole()->id];
        $this->assertEquals(0, $line['multibuy']);
        $this->assertSame(['more' => 1, 'percent' => 5.0], $line['next_tier']);
        $this->get(route('cart'))->assertOk()->assertSee('Add 1 more to save 5%');

        $cart->items()->update(['quantity' => 2]);
        $this->get(route('cart'))->assertOk()->assertSee('Multi-buy 5% off')->assertSee('Multi-buy savings')->assertSee('MVR 5.00')->assertSee('Add 1 more to save 10%');

        $cart->items()->update(['quantity' => 4]);
        $deals = $this->cartDeals();
        $this->assertEquals(20, $deals['multibuy_discount'], '10% of 200');
        $this->assertNull($deals['lines'][$cart->items()->sole()->id]['next_tier']);
        $this->get(route('cart'))->assertOk()->assertSee('Multi-buy 10% off')->assertSee('MVR 180.00');

        $order = $this->placeOrder($this->customer);
        $item = $order->items()->sole();
        $this->assertEquals(20, $item->multibuy_discount);
        $this->assertEquals(0, $item->shop_code_discount);
        $this->assertEquals(20, $order->shop_discount);
        $this->assertEquals(180 + 75, $order->total_amount);
        $part = $order->sellerOrders()->sole();
        $this->assertEquals(200, $part->subtotal);
        $this->assertEquals(20, $part->shop_discount);
        $this->assertEquals(18, $part->commission_amount, '10% of the discounted 180');
        $this->assertEquals(162, $part->seller_earnings);
        $this->assertOrderAddsUp($order);

        $this->get(route('orders.show', $order))->assertOk()->assertSee('Multi-buy savings')->assertSee('MVR 20.00');
    }

    public function test_a_products_variants_count_together(): void
    {
        $product = $this->productOf($this->shop, 50, ['has_variants' => true]);
        $small = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 40, 'stock_quantity' => 10, 'is_active' => true]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 60, 'stock_quantity' => 10, 'is_active' => true]);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 10]], [$product]);
        $this->cartFor($this->customer, [[$product, 1, $small->id], [$product, 1, $large->id]]);
        $this->actingAs($this->customer);

        $deals = $this->cartDeals();
        $this->assertEquals(10, $deals['multibuy_discount'], '4.00 + 6.00');

        $order = $this->placeOrder($this->customer);
        $byVariant = $order->items->keyBy('product_variant_id');
        $this->assertEquals(4, $byVariant[$small->id]->multibuy_discount);
        $this->assertEquals(6, $byVariant[$large->id]->multibuy_discount);
        $this->assertOrderAddsUp($order);
    }

    public function test_a_mix_and_match_group_counts_its_products_together(): void
    {
        $tee = $this->productOf($this->shop, 25);
        $polo = $this->productOf($this->shop, 35);
        $mug = $this->productOf($this->shop, 30);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 10]], [$tee, $polo], 'Tees');
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 50]], [$mug]); // its own offer: doesn't count with the tees
        $cart = $this->cartFor($this->customer, [[$tee, 1], [$polo, 1], [$mug, 1]]);
        $this->actingAs($this->customer);

        $lines = $this->cartDeals()['lines'];
        $ids = $cart->items()->pluck('id', 'product_id');
        $this->assertEquals(2.50, $lines[$ids[$tee->id]]['multibuy']);
        $this->assertEquals(3.50, $lines[$ids[$polo->id]]['multibuy']);
        $this->assertEquals(0, $lines[$ids[$mug->id]]['multibuy']);
        $this->assertSame('Tees', $lines[$ids[$tee->id]]['group']);

        $cart->items()->where('product_id', $polo->id)->delete();
        $this->get(route('cart'))->assertOk()->assertSee('Add 1 more from “Tees” to save 10%');
    }

    public function test_the_campaign_price_is_the_base(): void
    {
        $product = $this->productOf($this->shop, 100);
        $campaign = Campaign::factory()->create(['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'discount_percent' => 20]);
        CampaignProduct::create(['campaign_id' => $campaign->id, 'product_id' => $product->id, 'seller_id' => $this->shop->id, 'discount_percent' => 20, 'approved_at' => now()]);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 10]], [$product]);
        $this->cartFor($this->customer, [[$product, 2]]);
        $this->actingAs($this->customer);

        $order = $this->placeOrder($this->customer);
        $item = $order->items()->sole();
        $this->assertEquals(80, $item->price, 'the campaign price');
        $this->assertEquals(16, $item->multibuy_discount, '10% of 160');
        $this->assertEquals(144 + 75, $order->total_amount);
        $this->assertOrderAddsUp($order);
    }

    public function test_multi_buy_comes_before_the_shop_code_and_the_shop_pays_for_both(): void
    {
        $product = $this->productOf($this->shop, 50);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 10]], [$product]);
        $this->codeFor($this->shop, 'TEN'); // 10% of what is left after multi-buy
        $this->cartFor($this->customer, [[$product, 2]]);
        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'ten'])->assertSessionHasNoErrors();

        $order = $this->placeOrder($this->customer);
        $item = $order->items()->sole();
        $this->assertEquals(10, $item->multibuy_discount);
        $this->assertEquals(9, $item->shop_code_discount, '10% of 90, not of 100');
        $this->assertEquals(81, $item->netTotal());

        $part = $order->sellerOrders()->sole();
        $this->assertEquals(100, $part->subtotal);
        $this->assertEquals(19, $part->shop_discount);
        $this->assertEquals(8.10, $part->commission_amount);
        $this->assertEquals(72.90, $part->seller_earnings);
        $this->assertOrderAddsUp($order);
    }

    public function test_carts_with_several_shops_keep_each_shops_offers_and_codes_apart(): void
    {
        $other = $this->shop('Reefline Marine');
        $mine = $this->productOf($this->shop, 30);
        $theirs = $this->productOf($other, 30);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 10]], [$mine]);
        $this->codeFor($other, 'REEF', ['type' => 'fixed', 'value' => 5]);
        $this->cartFor($this->customer, [[$mine, 2], [$theirs, 2]]);
        $this->actingAs($this->customer)->post(route('cart.shopCode.apply'), ['shop_code' => 'REEF'])->assertSessionHasNoErrors();

        $order = $this->placeOrder($this->customer);
        $mineItem = $order->items->firstWhere('product_id', $mine->id);
        $theirItem = $order->items->firstWhere('product_id', $theirs->id);
        $this->assertEquals(6, $mineItem->multibuy_discount);
        $this->assertEquals(0, $mineItem->shop_code_discount);
        $this->assertEquals(0, $theirItem->multibuy_discount);
        $this->assertEquals(5, $theirItem->shop_code_discount);
        $this->assertEquals(6, $order->sellerOrders()->where('seller_id', $this->shop->id)->sole()->shop_discount);
        $this->assertEquals(5, $order->sellerOrders()->where('seller_id', $other->id)->sole()->shop_discount);
        $this->assertOrderAddsUp($order);
    }

    public function test_the_api_cart_and_order_show_the_multi_buy_saving(): void
    {
        $product = $this->productOf($this->shop, 50);
        $this->offerFor($this->shop, [['min_qty' => 2, 'percent' => 10]], [$product]);
        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertCreated()
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.shop_discount', 10)
            ->assertJsonPath('data.total', 90);

        $order = $this->placeOrder($this->customer);
        $resource = (new \App\Http\Resources\OrderResource($order->load('items')))->toArray(request());
        $this->assertEquals(10, $resource['shop_discount']);
        $this->assertEquals(10, $resource['items'][0]['multibuy_discount']);
        $this->assertEquals(90 + 75, $order->total_amount);
    }

    // ---- Rounding ---------------------------------------------------------------------------------

    public function test_shares_are_rounded_to_the_laari_and_always_add_up(): void
    {
        $this->assertSame([1 => 34, 2 => 33, 3 => 33], ShopDiscountService::allocate(100, [1 => 1, 2 => 1, 3 => 1]));
        $this->assertSame([7 => 1000, 9 => 200], ShopDiscountService::allocate(1200, [7 => 9999, 9 => 1999]));
        $this->assertSame([1 => 0, 2 => 1], ShopDiscountService::allocate(1, [1 => 5, 2 => 6]), 'ties go to the bigger line');
        $this->assertSame([1 => 0, 2 => 0], ShopDiscountService::allocate(0, [1 => 5, 2 => 6]));

        foreach ([[99999, [333, 333, 334, 1]], [101, [7, 11, 13, 17, 19]], [5000, [2500, 2500]], [1, [1, 1, 1]]] as [$total, $weights]) {
            $this->assertSame($total, array_sum(ShopDiscountService::allocate($total, $weights)));
        }

        $this->assertSame(100, ShopDiscountService::percentOf(1999, 5), '5% of 19.99 = 0.9995, rounded half up');
        $this->assertSame(1200, ShopDiscountService::percentOf(11998, 10));
        $this->assertSame(42, ShopDiscountService::percentOf(333, 12.5));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\SavedItem;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\BackInStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShoppingFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $this->seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => Category::factory()->create(['status' => 'active'])->id,
            'seller_id' => $this->seller->id,
            'is_active' => true,
            'stock_quantity' => 10,
            'price' => 100,
            'compare_price' => null,
        ], $attributes));
    }

    public function test_guests_can_fill_a_cart_and_it_follows_them_into_their_account(): void
    {
        $product = $this->product(['name' => ['en' => 'Woven mat']]);
        $user = User::factory()->create(['password' => bcrypt('secret-pass-123')]);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect(route('cart'));
        $this->get(route('cart'))->assertOk()->assertSee('Woven mat')->assertSee('Sign in to check out');

        // Checkout needs an account; the guest cart is merged on sign-in.
        $this->get(route('checkout'))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123']);
        $this->assertAuthenticatedAs($user);

        $cart = Cart::where('user_id', $user->id)->where('status', 'active')->first();
        $this->assertNotNull($cart);
        $this->assertSame(2, (int) $cart->items()->sum('quantity'));
    }

    public function test_guest_cart_merges_into_an_existing_account_cart(): void
    {
        $product = $this->product();
        $user = User::factory()->create(['password' => bcrypt('secret-pass-123')]);
        $existing = Cart::create(['user_id' => $user->id, 'session_id' => 'old', 'status' => 'active']);
        $existing->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123']);

        $this->assertSame(3, (int) $existing->items()->sum('quantity'));
        $this->assertSame(1, $existing->items()->count());
    }

    public function test_one_shopper_cannot_touch_another_shoppers_cart_item(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = Cart::first()->items()->first();

        $this->flushSession();
        $this->put(route('cart.update', $item), ['quantity' => 5])->assertForbidden();
    }

    public function test_cart_page_quantity_and_save_for_later(): void
    {
        $product = $this->product(['name' => ['en' => 'Coir rope'], 'stock_quantity' => 4]);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = Cart::where('user_id', $user->id)->first()->items()->first();

        $this->get(route('cart'))->assertOk()->assertSee('Proceed to Checkout')->assertSee(route('checkout'));

        // Quantity is capped at the stock
        $this->put(route('cart.update', $item), ['quantity' => 9])->assertRedirect(route('cart'));
        $this->assertSame(4, $item->fresh()->quantity);

        $this->post(route('cart.saveForLater', $item))->assertRedirect(route('cart'));
        $this->assertModelMissing($item);
        $saved = SavedItem::where('user_id', $user->id)->firstOrFail();
        $this->get(route('cart'))->assertSee('Saved for later')->assertSee('Coir rope');

        $this->post(route('saved.moveToCart', $saved))->assertRedirect(route('cart'));
        $this->assertModelMissing($saved);
        $this->assertSame(4, (int) Cart::where('user_id', $user->id)->first()->items()->sum('quantity'));

        $other = SavedItem::create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id]);
        $this->post(route('saved.moveToCart', $other))->assertForbidden();
    }

    public function test_search_suggestions(): void
    {
        $this->product(['name' => ['en' => 'Coconut oil'], 'brand' => 'Maafushi Fresh']);
        $this->product(['name' => ['en' => 'Dry bag']]);

        $this->getJson(route('search.suggest', ['q' => 'coco']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Coconut oil');

        $this->getJson(route('search.suggest', ['q' => 'maafushi']))->assertJsonPath('brands.0.name', 'Maafushi Fresh');
        $this->getJson(route('search.suggest', ['q' => 'c']))->assertJsonCount(0, 'products');
    }

    public function test_compare_up_to_four_products(): void
    {
        $products = collect(range(1, 5))->map(fn ($i) => $this->product(['name' => ['en' => "Item {$i}"], 'sku' => "CMP-{$i}"]));

        foreach ($products->take(4) as $p) {
            $this->post(route('compare.toggle', $p));
        }
        $this->post(route('compare.toggle', $products[4]));
        $this->assertCount(4, session('compare'));

        $this->get(route('compare'))->assertOk()->assertSee('CMP-1')->assertSee('CMP-4')->assertDontSee('CMP-5');

        $this->post(route('compare.toggle', $products[0]));
        $this->assertCount(3, session('compare'));
        $this->get('/')->assertSee('Compare (3)');
    }

    public function test_reviews_with_verified_badge_and_helpful_votes(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create(['name' => 'Aisha']);
        Order::factory()->create(['user_id' => $buyer->id, 'status' => 'delivered'])->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

        $this->post(route('reviews.store', $product), ['rating' => 5, 'comment' => 'Lovely weave'])->assertRedirect(route('login'));

        $this->actingAs($buyer)->post(route('reviews.store', $product), ['rating' => 5, 'title' => 'Beautiful', 'comment' => 'Lovely weave and quick delivery'])->assertRedirect();
        $this->actingAs($buyer)->post(route('reviews.store', $product), ['rating' => 4, 'comment' => 'Still lovely a month later'])->assertRedirect();

        $review = ProductReview::where('product_id', $product->id)->sole();
        $this->assertTrue($review->verified_purchase);
        $this->assertSame(4, $review->rating);

        // Own vote doesn't count; another shopper's counts once
        $this->post(route('reviews.helpful', $review));
        $voter = User::factory()->create();
        $this->actingAs($voter)->post(route('reviews.helpful', $review));
        $this->actingAs($voter)->post(route('reviews.helpful', $review));
        $this->assertSame(1, $review->fresh()->helpful_count);

        $this->get(route('products.show', $product))
            ->assertSee('Verified purchase')
            ->assertSee('Still lovely a month later')
            ->assertSee('1 person found this helpful');

        $this->actingAs($voter)->post(route('reviews.store', $product), ['rating' => 9, 'comment' => 'x'])->assertSessionHasErrors(['rating', 'comment']);
    }

    public function test_questions_and_seller_answers(): void
    {
        $product = $this->product();
        $shopper = User::factory()->create();

        $this->actingAs($shopper)->post(route('questions.store', $product), ['question' => 'Does it come in blue?'])->assertRedirect();
        $question = ProductQuestion::sole();

        $this->get(route('products.show', $product))->assertSee('Does it come in blue?')->assertSee('Waiting for the shop to answer.');

        $this->actingAs($shopper)->post(route('questions.answer', $question), ['answer' => 'Yes'])->assertForbidden();

        $this->actingAs($this->seller)->get(route('seller.questions'))->assertOk()->assertSee('Does it come in blue?');
        $this->actingAs($this->seller)->post(route('questions.answer', $question), ['answer' => 'Yes, in two blues.'])->assertRedirect();

        $this->get(route('products.show', $product))->assertSee('Yes, in two blues.')->assertDontSee('Waiting for the shop to answer.');
        $this->actingAs($this->seller)->get(route('seller.questions'))->assertDontSee('Does it come in blue?');
    }

    public function test_back_in_stock_alert_is_sent_once(): void
    {
        Notification::fake();
        $product = $this->product(['stock_quantity' => 0]);

        $this->get(route('products.show', $product))->assertSee('Notify me');
        $this->post(route('stock-alerts.store', $product), ['email' => 'Shopper@Example.com'])->assertRedirect();
        $this->post(route('stock-alerts.store', $product), ['email' => 'shopper@example.com']);
        $this->assertSame(1, StockAlert::count());

        $product->update(['stock_quantity' => 5]);
        $product->update(['stock_quantity' => 3]);

        Notification::assertSentTimes(BackInStock::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, BackInStock::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'shopper@example.com');
        $this->assertNotNull(StockAlert::first()->notified_at);
    }

    public function test_deal_countdown_shows_when_the_sale_has_an_end(): void
    {
        $product = $this->product(['price' => 90, 'compare_price' => 120, 'flash_sale_ends_at' => now()->addHours(5)]);
        $plain = $this->product(['price' => 90, 'compare_price' => 120, 'name' => ['en' => 'No end'], 'seller_id' => User::factory()->create()->id]);

        $this->get(route('products.show', $product))
            ->assertSee('Deal ends in')
            ->assertSee('data-countdown="'.$product->flash_sale_ends_at->toIso8601String().'"', false);

        $this->flushSession();
        $this->get(route('products.show', $plain))->assertDontSee('data-countdown="', false);

        $product->update(['flash_sale_ends_at' => now()->subMinute()]);
        $this->flushSession();
        $this->get(route('products.show', $product))->assertDontSee('data-countdown="', false);
    }

    public function test_seller_can_set_a_sale_end(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->seller)->post('/seller/products', [
            'name_en' => 'Snorkel', 'sku' => 'SN-1', 'category_id' => $category->id,
            'price' => 90, 'compare_price' => 120, 'stock_quantity' => 3,
            'flash_sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertNotNull(Product::where('sku', 'SN-1')->value('flash_sale_ends_at'));
    }

    public function test_frequently_bought_together_and_add_many(): void
    {
        $mat = $this->product(['name' => ['en' => 'Woven mat']]);
        $rope = $this->product(['name' => ['en' => 'Coir rope'], 'seller_id' => User::factory()->create()->id]);
        $order = Order::factory()->create();
        $order->items()->create(['product_id' => $mat->id, 'quantity' => 1, 'price' => 100]);
        $order->items()->create(['product_id' => $rope->id, 'quantity' => 1, 'price' => 100]);

        $this->get(route('products.show', $mat))->assertSee('Frequently bought together')->assertSee('Coir rope');

        $this->post(route('cart.addMany'), ['product_ids' => [$mat->id, $rope->id]])->assertRedirect(route('cart'));
        $this->assertSame(2, Cart::first()->items()->count());
    }

    public function test_buy_again_puts_available_items_back_in_the_cart(): void
    {
        $user = User::factory()->create();
        $inStock = $this->product();
        $soldOut = $this->product(['stock_quantity' => 0]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $order->items()->create(['product_id' => $inStock->id, 'quantity' => 2, 'price' => 100]);
        $order->items()->create(['product_id' => $soldOut->id, 'quantity' => 1, 'price' => 100]);

        $this->actingAs(User::factory()->create())->post(route('orders.buyAgain', $order))->assertForbidden();

        $this->actingAs($user)->post(route('orders.buyAgain', $order))->assertRedirect(route('cart'));
        $items = Cart::where('user_id', $user->id)->first()->items;
        $this->assertCount(1, $items);
        $this->assertSame(2, $items->first()->quantity);
    }

    public function test_newsletter_signup(): void
    {
        $this->post(route('newsletter.store'), ['email' => 'Fan@Example.com'])->assertRedirect();
        $this->post(route('newsletter.store'), ['email' => 'fan@example.com']);
        $this->assertSame(1, NewsletterSubscriber::count());
        $this->post(route('newsletter.store'), ['email' => 'nope'])->assertSessionHasErrors('email');
    }

    public function test_brand_page_and_whatsapp_links(): void
    {
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hand line kit']]);
        $this->product(['brand' => 'Other', 'name' => ['en' => 'Not this']]);

        $this->get(route('brands.show', 'Reefline'))->assertOk()->assertSee('Hand line kit')->assertDontSee('Not this');
        $this->get(route('brands.show', 'Nobody'))->assertNotFound();

        $this->get('/help')->assertDontSee('wa.me/960');
        Setting::set(['whatsapp_number' => '+960 777 1234']);
        $this->get('/help')->assertSee('https://wa.me/9607771234', false);
    }
}

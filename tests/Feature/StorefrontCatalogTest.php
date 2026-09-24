<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected Category $crafts;

    protected Category $food;

    protected User $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crafts = Category::create(['name' => ['en' => 'Handmade & Crafts'], 'slug' => 'handmade-crafts', 'status' => 'active']);
        $this->food = Category::create(['name' => ['en' => 'Food & Groceries'], 'slug' => 'food-groceries', 'status' => 'active']);
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts', 'city' => 'Hithadhoo']);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->crafts->id,
            'seller_id' => $this->shop->id,
            'is_active' => true,
            'stock_quantity' => 10,
            'compare_price' => null,
        ], $attributes));
    }

    public function test_department_pages_load_by_slug(): void
    {
        $this->product(['name' => ['en' => 'Woven mat']]);
        $this->product(['name' => ['en' => 'Rihaakuru jar'], 'category_id' => $this->food->id]);

        $this->get('/categories/handmade-crafts')
            ->assertOk()
            ->assertSee('Handmade &amp; Crafts', false)
            ->assertSee('Woven mat')
            ->assertDontSee('Rihaakuru jar');

        $this->get('/categories/does-not-exist')->assertNotFound();

        $this->crafts->update(['status' => 'inactive']);
        $this->get('/categories/handmade-crafts')->assertNotFound();
    }

    public function test_filters_combine_and_show_counts(): void
    {
        $this->product(['name' => ['en' => 'Cheap mat'], 'price' => 50, 'brand' => 'Reed Co']);
        $this->product(['name' => ['en' => 'Big mat'], 'price' => 900, 'brand' => 'Reed Co']);
        $this->product(['name' => ['en' => 'Lacquer box'], 'price' => 400, 'brand' => 'Thulhaadhoo']);
        $this->product(['name' => ['en' => 'Sold out vase'], 'price' => 300, 'stock_quantity' => 0]);

        $this->get('/shop?brand[]=Reed%20Co&max_price=500')
            ->assertOk()
            ->assertSee('Cheap mat')
            ->assertDontSee('Big mat')
            ->assertDontSee('Lacquer box')
            ->assertSee('1–1 of 1 results');

        $this->get('/shop?in_stock=1')->assertDontSee('Sold out vase')->assertSee('Lacquer box');
        $this->get('/shop')->assertSee('Sold out vase')->assertSee('Out of stock');
    }

    public function test_deals_only_lists_marked_down_products(): void
    {
        $this->product(['name' => ['en' => 'Snorkel set'], 'price' => 650, 'compare_price' => 780]);
        $this->product(['name' => ['en' => 'Dry bag'], 'price' => 320]);

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Snorkel set')
            ->assertDontSee('Dry bag')
            ->assertSee('Save MVR 130.00');

        $this->get('/shop?deals=1')->assertSee('Snorkel set')->assertDontSee('Dry bag');
    }

    public function test_sorting_by_price(): void
    {
        $this->product(['name' => ['en' => 'Middle item'], 'price' => 200]);
        $this->product(['name' => ['en' => 'Cheapest item'], 'price' => 10]);
        $this->product(['name' => ['en' => 'Priciest item'], 'price' => 999]);

        $this->get('/shop?sort=price_low')->assertSeeInOrder(['Cheapest item', 'Middle item', 'Priciest item']);
        $this->get('/shop?sort=price_high')->assertSeeInOrder(['Priciest item', 'Middle item', 'Cheapest item']);
    }

    public function test_search_matches_name_brand_and_sku_and_keeps_department(): void
    {
        $this->product(['name' => ['en' => 'Coconut oil'], 'sku' => 'MF-COIL']);
        $this->product(['name' => ['en' => 'Hand line kit'], 'brand' => 'Reefline']);
        $this->product(['name' => ['en' => 'Coconut sweets'], 'category_id' => $this->food->id]);

        $this->get('/search?q=coconut')->assertOk()->assertSee('Coconut oil')->assertSee('Coconut sweets')->assertDontSee('Hand line kit');
        $this->get('/search?q=reefline')->assertSee('Hand line kit');
        $this->get('/search?q=MF-COIL')->assertSee('Coconut oil');
        $this->get('/search?q=coconut&category=food-groceries')->assertSee('Coconut sweets')->assertDontSee('Coconut oil');
        $this->get('/search?q=')->assertRedirect(route('shop'));
    }

    public function test_list_view_renders(): void
    {
        $this->product(['name' => ['en' => 'Woven mat'], 'model' => 'WM-2']);

        $this->get('/shop?view=list')->assertOk()->assertSee('WM-2')->assertSee('aria-current="true"', false);
    }

    public function test_seller_shop_page(): void
    {
        $this->product(['name' => ['en' => 'Woven mat']]);
        $this->product(['name' => ['en' => 'Somebody else’s item'], 'seller_id' => User::factory()->create()->id]);

        $this->get(route('sellers.show', $this->shop))
            ->assertOk()
            ->assertSee('Island Crafts')
            ->assertSee('Hithadhoo')
            ->assertSee('Woven mat')
            ->assertDontSee('Somebody else’s item');

        $this->get(route('sellers.show', User::factory()->create()))->assertNotFound();
    }

    public function test_product_page_shows_buy_box_specs_seller_and_reviews(): void
    {
        $product = $this->product(['name' => ['en' => 'Woven mat'], 'brand' => 'Reed Co', 'model' => 'WM-2', 'price' => 360, 'compare_price' => 450]);
        ProductReview::create(['product_id' => $product->id, 'reviewer_name' => 'Aisha', 'reviewer_email' => 'a@example.com', 'rating' => 5, 'comment' => 'Beautiful work', 'is_approved' => true]);
        ProductReview::create(['product_id' => $product->id, 'reviewer_name' => 'Spam', 'reviewer_email' => 's@example.com', 'rating' => 1, 'comment' => 'Unapproved text', 'is_approved' => false]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Save MVR 90.00')
            ->assertSee('Key features')
            ->assertSee('WM-2')
            ->assertSee('Island Crafts')
            ->assertSee(route('sellers.show', $this->shop))
            ->assertSee('Beautiful work')
            ->assertDontSee('Unapproved text')
            ->assertSee(route('categories.show', $this->crafts));
    }

    public function test_recently_viewed_products_follow_the_shopper(): void
    {
        $first = $this->product(['name' => ['en' => 'First look']]);
        $second = $this->product(['name' => ['en' => 'Second look'], 'category_id' => $this->food->id, 'seller_id' => User::factory()->create()->id]);

        $this->get(route('products.show', $first))->assertOk();
        $this->get(route('products.show', $second))->assertSee('Recently viewed')->assertSee('First look');
    }

    public function test_home_page_has_deals_departments_and_shops(): void
    {
        $this->product(['name' => ['en' => 'Snorkel set'], 'price' => 650, 'compare_price' => 780]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Today&#039;s deals', false)
            ->assertSee('Shop by department')
            ->assertSee('Snorkel set')
            ->assertSee('Shops on iruali')
            ->assertSee('Island Crafts');
    }

    public function test_help_page_shows_delivery_fees(): void
    {
        $this->get('/help')->assertOk()->assertSee('Delivery &amp; fees', false)->assertSee('MVR 25.00')->assertSee('MVR 75.00');
    }

    public function test_cart_add_refuses_more_than_the_stock(): void
    {
        $product = $this->product(['stock_quantity' => 2]);
        $this->actingAs(User::factory()->create());

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect(route('cart'));
        $this->from(route('products.show', $product))
            ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('notification', fn ($n) => $n['type'] === 'error');

        $this->get('/')->assertSee('id="session-notification"', false);
    }
}

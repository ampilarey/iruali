<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\BusinessProfile;
use App\Models\Category;
use App\Models\Island;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsQuotes;
use Tests\TestCase;

/**
 * Bulk quotes, asking: "Request a bulk quote" on the product and shop pages, the form (quantity
 * from the product's minimum, delivery island, needed-by date, notes, the business details), one
 * open request per customer and product, the rate limit and the shop's minimum on the product form.
 */
class BulkQuoteRequestsTest extends TestCase
{
    use BuildsQuotes, RefreshDatabase;

    protected User $shopUser;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        $this->shopUser = $this->shop('Island Crafts');
        $this->product = $this->productOf($this->shopUser, 120, ['name' => ['en' => 'Coconut soap'], 'stock_quantity' => 500]);
    }

    public function test_signed_in_customers_see_request_a_bulk_quote_on_the_product_page_and_guests_are_asked_to_sign_in(): void
    {
        $customer = $this->customer();
        $form = route('quotes.create', ['product' => $this->product->id]);

        $this->actingAs($customer)->get(route('products.show', $this->product))->assertOk()
            ->assertSee('data-bulk-quote', false)->assertSee('Request a bulk quote')->assertSee($form, false)
            ->assertSee('Ask Island Crafts for a price on 10 or more.');

        auth()->logout();
        $this->get(route('products.show', $this->product))->assertOk()->assertSee('Sign in to request a bulk quote');
        $this->get($form)->assertRedirect(route('login'));

        // Not on the shop's own products, nor while it is on holiday
        $this->actingAs($this->shopUser)->get(route('products.show', $this->product))->assertOk()->assertDontSee('data-bulk-quote', false);
        $this->shopUser->forceFill(['holiday_mode' => true, 'holiday_until' => today()->addDays(5)])->save();
        $this->actingAs($customer)->get(route('products.show', $this->product))->assertOk()->assertDontSee('data-bulk-quote', false);
        $this->get($form)->assertRedirect(route('products.show', $this->product));
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_the_shop_page_offers_a_quote_on_any_of_its_products(): void
    {
        $other = $this->productOf($this->shopUser, 45, ['name' => ['en' => 'Palm basket']]);
        $this->productOf($this->shopUser, 45, ['name' => ['en' => 'Hidden draft'], 'is_active' => false]);
        $customer = $this->customer();

        $this->actingAs($customer)->get(route('sellers.show', $this->shopUser))->assertOk()
            ->assertSee('data-shop-bulk-quote', false)->assertSee(route('quotes.create', ['shop' => $this->shopUser->id]), false);

        $this->get(route('quotes.create', ['shop' => $this->shopUser->id]))->assertOk()
            ->assertSee('data-quote-product-picker', false)->assertSee('Coconut soap')->assertSee('Palm basket')->assertDontSee('Hidden draft');
        $this->get(route('quotes.create', ['product' => $other->id]))->assertOk()->assertSee('data-quote-form', false)->assertSee('Palm basket');

        $this->get(route('quotes.create', ['shop' => User::factory()->create()->id]))->assertNotFound();
    }

    public function test_the_form_is_prefilled_from_the_business_profile_and_default_address(): void
    {
        $customer = $this->customer();
        BusinessProfile::create(['user_id' => $customer->id, 'company_name' => 'Blue Lagoon Cafe Pvt Ltd', 'tin' => '1023456GST501', 'business_address' => 'M. Lagoon View, Malé']);
        $island = Island::create(['name' => ['en' => 'Thinadhoo'], 'atoll' => 'Gaafu Dhaalu', 'is_active' => true]);
        $address = new Address(['recipient_name' => 'Aminath', 'phone' => '7771234', 'island_id' => $island->id, 'island' => 'Thinadhoo', 'atoll' => 'Gaafu Dhaalu', 'house_name_or_street' => 'H. Sea Breeze', 'country' => 'Maldives', 'is_default' => true]);
        $address->user_id = $customer->id;
        $address->save();

        $this->actingAs($customer)->get(route('quotes.create', ['product' => $this->product->id]))->assertOk()
            ->assertSee('value="Blue Lagoon Cafe Pvt Ltd"', false)->assertSee('value="1023456GST501"', false)->assertSee('value="M. Lagoon View, Malé"', false)
            ->assertSee('value="'.$island->id.'" data-zone', false)
            ->assertSee('min="10"', false)
            ->assertSee('Update the business details on my account');
    }

    public function test_a_customer_sends_a_request_and_the_shop_is_emailed(): void
    {
        $customer = $this->customer();

        $response = $this->sendRequest($customer, $this->product)->assertSessionHasNoErrors();
        $quote = QuoteRequest::sole();
        $response->assertRedirect(route('quotes.show', $quote));

        $this->assertSame('new', $quote->status);
        $this->assertSame($customer->id, $quote->customer_id);
        $this->assertSame($this->shopUser->id, $quote->seller_id);
        $this->assertSame(40, $quote->quantity);
        $this->assertSame('Coconut soap', $quote->product_name);
        $this->assertSame('Hithadhoo, Addu', $quote->deliveryPlace());
        $this->assertSame('2026-10-25', $quote->needed_by->toDateString());
        $this->assertSame('Sun Island Resort Pvt Ltd', $quote->business_name);
        $this->assertSame('1012345GST501', $quote->business_tin, 'the TIN is stored normalised');

        // The business details are completed on the account, for checkout and the next request
        $profile = BusinessProfile::where('user_id', $customer->id)->sole();
        $this->assertSame(['Sun Island Resort Pvt Ltd', '1012345GST501', 'M. Sunny Building, Malé'], [$profile->company_name, $profile->tin, $profile->business_address]);

        Notification::assertSentTo($this->shopUser, QuoteRequested::class, fn ($n) => $n->quote->is($quote));
        Notification::assertNotSentTo($customer, QuoteRequested::class);

        $this->actingAs($customer)->get(route('quotes.show', $quote))->assertOk()
            ->assertSee('Waiting for Island Crafts to reply.')->assertSee('Sun Island Resort Pvt Ltd')->assertSee('Hithadhoo, Addu');
        $this->get(route('quotes.index'))->assertOk()->assertSee($quote->label())->assertSee('Coconut soap');
    }

    public function test_a_picked_island_is_kept_with_its_name_and_the_profile_can_be_left_alone(): void
    {
        $customer = $this->customer();
        $island = Island::create(['name' => ['en' => 'Fuvahmulah'], 'atoll' => 'Gnaviyani', 'is_active' => true]);

        $this->sendRequest($customer, $this->product, ['island_id' => $island->id, 'island' => '', 'atoll' => '', 'save_business' => '0', 'needed_by' => ''])->assertSessionHasNoErrors();

        $quote = QuoteRequest::sole();
        $this->assertSame($island->id, $quote->island_id);
        $this->assertSame('Fuvahmulah, Gnaviyani', $quote->deliveryPlace());
        $this->assertNull($quote->needed_by);
        $this->assertSame(0, BusinessProfile::count(), 'not saved when the box is unticked');
    }

    public function test_the_request_is_validated(): void
    {
        $customer = $this->customer();

        $this->sendRequest($customer, $this->product, ['quantity' => 9])->assertSessionHasErrors('quantity');
        $this->sendRequest($customer, $this->product, ['quantity' => 100000])->assertSessionHasErrors('quantity');
        $this->sendRequest($customer, $this->product, ['island' => '', 'island_id' => ''])->assertSessionHasErrors('island');
        $this->sendRequest($customer, $this->product, ['island_id' => 999999])->assertSessionHasErrors('island_id');
        $this->sendRequest($customer, $this->product, ['needed_by' => '2026-10-14'])->assertSessionHasErrors('needed_by');
        $this->sendRequest($customer, $this->product, ['buyer_business_name' => '', 'buyer_business_address' => ''])->assertSessionHasErrors(['buyer_business_name', 'buyer_business_address']);
        $this->sendRequest($customer, $this->product, ['buyer_tin' => '12345'])->assertSessionHasErrors('buyer_tin');
        $this->sendRequest($customer, $this->product, ['notes' => str_repeat('a', 2001)])->assertSessionHasErrors('notes');
        $this->assertSame(0, QuoteRequest::count());

        // Exactly the minimum is fine
        $this->sendRequest($customer, $this->product, ['quantity' => 10])->assertSessionHasNoErrors();
        $this->assertSame(10, QuoteRequest::sole()->quantity);
    }

    public function test_a_product_sold_in_options_needs_one_of_its_options(): void
    {
        $customer = $this->customer();
        $hoodie = $this->productOf($this->shopUser, 300, ['name' => ['en' => 'Staff hoodie'], 'has_variants' => true]);
        $medium = ProductVariant::factory()->for($hoodie)->create(['attributes' => ['Size' => 'M'], 'stock_quantity' => 80, 'is_active' => true]);
        $retired = ProductVariant::factory()->for($hoodie)->create(['attributes' => ['Size' => 'XL'], 'stock_quantity' => 5, 'is_active' => false]);

        $this->actingAs($customer)->get(route('quotes.create', ['product' => $hoodie->id]))->assertOk()->assertSee('name="product_variant_id"', false)->assertDontSee('value="'.$retired->id.'"', false);

        $this->sendRequest($customer, $hoodie)->assertSessionHasErrors('product_variant_id');
        $this->sendRequest($customer, $hoodie, ['product_variant_id' => $retired->id])->assertSessionHasErrors('product_variant_id');
        $this->assertSame(0, QuoteRequest::count());

        $this->sendRequest($customer, $hoodie, ['product_variant_id' => $medium->id])->assertSessionHasNoErrors();
        $quote = QuoteRequest::sole();
        $this->assertSame($medium->id, $quote->product_variant_id);
        $this->assertSame('M', $quote->variant_name);
    }

    public function test_one_open_request_per_customer_and_product(): void
    {
        $customer = $this->customer();
        $this->sendRequest($customer, $this->product)->assertSessionHasNoErrors();
        $first = QuoteRequest::sole();

        // The form and a second send both lead back to the open request
        $this->get(route('quotes.create', ['product' => $this->product->id]))->assertRedirect(route('quotes.show', $first));
        $this->sendRequest($customer, $this->product, ['quantity' => 60])->assertRedirect(route('quotes.show', $first));
        $this->assertSame(1, QuoteRequest::count());
        $this->get(route('products.show', $this->product))->assertOk()->assertSee('See your quote request')->assertSee(route('quotes.show', $first), false);

        // Another customer, or another product, is a request of its own
        $this->sendRequest($this->customer(), $this->product)->assertSessionHasNoErrors();
        $this->sendRequest($customer, $this->productOf($this->shopUser, 60))->assertSessionHasNoErrors();
        $this->assertSame(3, QuoteRequest::count());

        // Once it is closed, the customer may ask again
        $first->forceFill(['status' => 'declined', 'declined_by' => 'shop'])->save();
        $this->sendRequest($customer, $this->product)->assertSessionHasNoErrors();
        $this->assertSame(4, QuoteRequest::count());
    }

    public function test_requests_are_rate_limited(): void
    {
        $customer = $this->customer();
        for ($i = 0; $i < 10; $i++) {
            $this->sendRequest($customer, $this->productOf($this->shopUser, 50))->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->sendRequest($customer, $this->productOf($this->shopUser, 50))->assertStatus(429);
        $this->assertSame(10, QuoteRequest::count());

        // Other customers are not held up, and the limit lifts after an hour
        $this->sendRequest($this->customer(), $this->product)->assertRedirect();
        $this->travel(61)->minutes();
        $this->sendRequest($customer, $this->productOf($this->shopUser, 50))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(12, QuoteRequest::count());
    }

    public function test_the_shop_sets_its_own_minimum_on_the_product_form(): void
    {
        // Named as the form names it, so saving does not change its address (slug)
        $product = $this->productOf($this->shopUser, 75, ['name' => ['en' => 'Reef-safe sunscreen'], 'category_id' => Category::factory()->create()->id]);
        $form = fn (array $extra) => array_merge([
            'name_en' => 'Reef-safe sunscreen', 'sku' => $product->sku, 'category_id' => $product->category_id,
            'price' => 75, 'stock_quantity' => 300, 'reorder_point' => 5,
        ], $extra);

        $this->actingAs($this->shopUser)->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('Bulk quotes')->assertSee('name="quote_min_quantity"', false);

        $this->put(route('seller.products.update', $product), $form(['quote_min_quantity' => '1']))->assertSessionHasErrors('quote_min_quantity');
        $this->put(route('seller.products.update', $product), $form(['quote_min_quantity' => '25']))->assertSessionHasNoErrors();
        $this->assertSame(25, (int) $product->fresh()->quote_min_quantity);

        $customer = $this->customer();
        $this->actingAs($customer)->get(route('products.show', $product->fresh()))->assertSee('for a price on 25 or more.');
        $this->sendRequest($customer, $product, ['quantity' => 24])->assertSessionHasErrors(['quantity' => 'Bulk quotes start at 25 units for this product.']);
        $this->sendRequest($customer, $product, ['quantity' => 25])->assertSessionHasNoErrors();

        // A form without the field leaves it alone; emptied, the default (10) applies again
        $this->actingAs($this->shopUser)->put(route('seller.products.update', $product), $form([]))->assertSessionHasNoErrors();
        $this->assertSame(25, (int) $product->fresh()->quote_min_quantity);
        $this->put(route('seller.products.update', $product), $form(['quote_min_quantity' => '']))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->quote_min_quantity);

        // New products take it too
        $this->post(route('seller.products.store'), $form(['sku' => 'NEW-SKU-1', 'quote_min_quantity' => '12']))->assertSessionHasNoErrors();
        $this->assertSame(12, (int) Product::where('sku', 'NEW-SKU-1')->sole()->quote_min_quantity);
    }

    public function test_a_shop_cannot_ask_itself_and_a_product_that_is_gone_cannot_be_quoted(): void
    {
        $this->sendRequest($this->shopUser, $this->product)->assertRedirect(route('products.show', $this->product));

        $gone = $this->productOf($this->shopUser, 10, ['is_active' => false]);
        $this->sendRequest($this->customer(), $gone)->assertSessionHasErrors('product_id');
        $this->assertSame(0, QuoteRequest::count());
    }
}

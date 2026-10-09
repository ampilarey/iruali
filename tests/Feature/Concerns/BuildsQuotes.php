<?php

namespace Tests\Feature\Concerns;

use App\Enums\QuoteStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\QuoteService;
use Illuminate\Testing\TestResponse;

/**
 * Customers, shops and quote requests for the bulk quote tests (with BuildsShopDeals for shops,
 * products, carts and placing orders).
 */
trait BuildsQuotes
{
    use BuildsShopDeals;

    protected function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['name' => 'Aminath Resort Buyer']);
    }

    protected function staffMember(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    /**
     * The request form as a customer sends it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function requestForm(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'quantity' => 40,
            'island_id' => '',
            'island' => 'Hithadhoo',
            'atoll' => 'Addu',
            'needed_by' => today()->addDays(10)->toDateString(),
            'notes' => 'For the staff canteen, delivered in one go.',
            'buyer_business_name' => 'Sun Island Resort Pvt Ltd',
            'buyer_tin' => '1012345 gst 501',
            'buyer_business_address' => 'M. Sunny Building, Malé',
            'save_business' => '1',
        ], $overrides);
    }

    protected function sendRequest(User $customer, Product $product, array $overrides = []): TestResponse
    {
        return $this->actingAs($customer)->post(route('quotes.store'), $this->requestForm($product, $overrides));
    }

    /**
     * A request straight in the database, in any state: ['status' => 'quoted', 'unit_price' => 80,
     * 'quoted_quantity' => 30, 'valid_until' => ...].
     *
     * @param  array<string, mixed>  $state
     */
    protected function quoteFor(User $customer, Product $product, array $state = []): QuoteRequest
    {
        $quote = new QuoteRequest([
            'product_id' => $product->id,
            'product_variant_id' => $state['product_variant_id'] ?? null,
            'product_name' => $product->getTranslation('name', 'en', false) ?: 'Product',
            'variant_name' => $state['variant_name'] ?? null,
            'quantity' => $state['quantity'] ?? 40,
            'delivery_island' => 'Hithadhoo',
            'delivery_atoll' => 'Addu',
            'needed_by' => today()->addDays(10)->toDateString(),
            'notes' => 'For the staff canteen.',
            'business_name' => 'Sun Island Resort Pvt Ltd',
            'business_tin' => '1012345GST501',
            'business_address' => 'M. Sunny Building, Malé',
        ]);
        $status = $state['status'] ?? QuoteStatus::New->value;
        $quoted = in_array($status, ['quoted', 'accepted', 'ordered', 'expired'], true);
        $quote->forceFill(array_merge([
            'customer_id' => $customer->id,
            'seller_id' => $product->seller_id,
            'status' => $status,
            'unit_price' => $quoted ? 80 : null,
            'quoted_quantity' => $quoted ? 30 : null,
            'list_price' => $quoted ? (float) $product->price : null,
            'valid_until' => $quoted ? today()->addDays(7)->toDateString() : null,
            'quoted_at' => $quoted ? now() : null,
        ], array_diff_key($state, array_flip(['product_variant_id', 'variant_name', 'quantity', 'status']))))->save();

        return $quote->fresh();
    }

    /**
     * Accept the quote as its customer (into their cart).
     */
    protected function accept(QuoteRequest $quote): TestResponse
    {
        return $this->actingAs($quote->customer)->post(route('quotes.accept', $quote));
    }

    protected function activeCart(User $customer): ?Cart
    {
        return Cart::where('user_id', $customer->id)->where('status', 'active')->latest('id')->first();
    }

    protected function pay(Order $order): Order
    {
        app(PaymentService::class)->confirm($order->fresh());

        return $order->fresh();
    }

    protected function quotes(): QuoteService
    {
        return app(QuoteService::class);
    }
}

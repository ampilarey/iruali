<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CreditInvoice;
use App\Services\CreditService;
use App\Services\OrderService;
use App\Services\SuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pay later for approved business buyers, and suggestions from products bought together.
 */
class PayLaterAndSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function product(float $price = 100): Product
    {
        return Product::factory()->create(['price' => $price, 'sale_price' => null, 'stock_quantity' => 50, 'is_active' => true, 'has_variants' => false, 'category_id' => Category::factory()->create()->id, 'seller_id' => User::factory()->create()->id]);
    }

    protected function cartFor(User $user, Product $product, int $quantity = 1): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price]);

        return $cart;
    }

    protected function placeOnCredit(User $user): array
    {
        return app(OrderService::class)->createOrderFromCart($user, [
            'shipping_address' => 'Blue Villa', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu', 'shipping_zip' => '',
            'shipping_phone' => '7771234', 'shipping_country' => 'Maldives', 'payment_method' => CreditService::METHOD,
        ]);
    }

    public function test_an_admin_opens_pay_later_and_the_business_orders_within_its_limit(): void
    {
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_greater_male' => 0, 'delivery_fee_islands' => 0]);
        $business = User::factory()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->assertArrayNotHasKey(CreditService::METHOD, $this->actingAs($business)->app->make(\App\Services\PaymentService::class)->methods());
        $this->actingAs($admin)->put(route('admin.customers.credit', $business), ['credit_limit' => 500, 'terms_days' => 14, 'status' => 'active'])->assertSessionHas('success');
        $this->actingAs($business);
        $this->assertArrayHasKey(CreditService::METHOD, app(\App\Services\PaymentService::class)->methods());

        $this->cartFor($business, $this->product(300));
        $result = $this->placeOnCredit($business);
        $this->assertTrue($result['success'], $result['message']);
        $order = $result['order']->fresh();
        $this->assertSame(CreditService::METHOD, $order->payment_method);
        $this->assertSame(today()->addDays(14)->toDateString(), $order->credit_due_on->toDateString());
        $this->assertEqualsWithDelta(200, app(CreditService::class)->available($business), 0.01);

        // Over the credit left: refused
        $this->cartFor($business, $this->product(300));
        $this->assertStringContainsString('more than your pay-later credit', $this->placeOnCredit($business)['message']);

        $this->get(route('account.credit'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($admin)->get(route('admin.customers.show', $business))->assertSee($order->order_number);
    }

    public function test_reminders_before_the_due_date_and_overdue_invoices_block_new_ones(): void
    {
        Notification::fake();
        $business = User::factory()->create();
        CreditAccount::create(['user_id' => $business->id, 'credit_limit' => 5000, 'terms_days' => 30, 'status' => 'active']);
        $soon = Order::factory()->create(['user_id' => $business->id, 'payment_method' => 'credit', 'payment_status' => 'pending', 'status' => 'processing', 'credit_due_on' => today()->addDays(2)]);
        $late = Order::factory()->create(['user_id' => $business->id, 'payment_method' => 'credit', 'payment_status' => 'pending', 'status' => 'delivered', 'credit_due_on' => today()->subDay()]);

        $this->artisan('credit:reminders')->expectsOutputToContain('Reminded: 1; overdue: 1')->assertSuccessful();
        Notification::assertSentTo($business, CreditInvoice::class, fn ($n) => $n->kind === 'due' && $n->order->is($soon));
        Notification::assertSentTo($business, CreditInvoice::class, fn ($n) => $n->kind === 'overdue' && $n->order->is($late));
        $this->artisan('credit:reminders')->expectsOutputToContain('Reminded: 0; overdue: 0');

        $this->assertStringContainsString('overdue', (string) app(CreditService::class)->problem($business, 10));
    }

    public function test_pairs_are_worked_out_from_paid_orders_and_suggested_in_the_cart(): void
    {
        [$soap, $towel, $lamp] = [$this->product(50), $this->product(80), $this->product(90)];
        foreach (range(1, 3) as $i) {
            $order = Order::factory()->create(['payment_status' => 'paid', 'status' => 'delivered']);
            foreach ([$soap, $towel] as $p) {
                OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'quantity' => 1, 'price' => $p->price]);
            }
        }
        $unpaid = Order::factory()->create(['payment_status' => 'pending', 'status' => 'pending']);
        foreach ([$soap, $lamp] as $p) {
            foreach (range(1, 3) as $i) {
                OrderItem::create(['order_id' => $unpaid->id, 'product_id' => $p->id, 'quantity' => 1, 'price' => $p->price]);
            }
        }

        $this->artisan('insights:compute-pairs')->expectsOutputToContain('Stored 2 product pairs')->assertSuccessful();
        $this->assertSame([$towel->id], app(SuggestionService::class)->pairedIds([$soap->id])->all());

        $shopper = User::factory()->create();
        $this->cartFor($shopper, $soap);
        $this->actingAs($shopper)->get(route('cart'))->assertOk()->assertSee('data-cart-suggestions', false)->assertSee($towel->name);
        $this->post(route('cart.add'), ['product_id' => $towel->id, 'quantity' => 1, 'suggested' => 'cart']);
        $this->assertSame(['shown' => 1, 'added' => 1, 'rate' => 100.0], app(SuggestionService::class)->report()['cart']);
        $this->assertSame(1, DB::table('suggestion_stats')->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DeliveryService;
use App\Services\DiscountService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automatic offers (no code), automatic free delivery, and printed discount card batches.
 */
class PromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected function cart(User $user, float $price): Cart
    {
        $product = Product::factory()->create(['price' => $price, 'sale_price' => null, 'stock_quantity' => 20, 'category_id' => Category::factory()->create()->id, 'seller_id' => User::factory()->create()->id]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);

        return $cart->fresh();
    }

    public function test_the_best_automatic_offer_applies_without_a_code(): void
    {
        $user = User::factory()->create();
        $cart = $this->cart($user, 600);
        Voucher::factory()->create(['code' => 'SPEND500', 'type' => 'fixed', 'amount' => 50, 'min_order' => 500, 'is_automatic' => true]);
        Voucher::factory()->create(['code' => 'TENPC', 'type' => 'percent', 'amount' => 10, 'min_order' => 1000, 'is_automatic' => true]); // not reached
        Voucher::factory()->create(['code' => 'TYPED', 'type' => 'fixed', 'amount' => 200]); // needs a code

        $this->actingAs($user);
        $voucher = app(DiscountService::class)->calculateVoucherDiscount($cart);
        $this->assertSame('SPEND500', $voucher['voucher']->code);
        $this->assertEqualsWithDelta(50, $voucher['amount'], 0.001);
        $this->get(route('cart'))->assertSee(__('Offer').' (SPEND500)');

        $order = app(OrderService::class)->createOrderFromCart($user, ['shipping_address' => 'A', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu', 'shipping_zip' => '', 'shipping_phone' => '7771234', 'shipping_country' => 'Maldives'])['order'];
        $this->assertSame('SPEND500', $order->voucher_code);
        $this->assertSame(1, Voucher::where('code', 'SPEND500')->value('used_count'));
    }

    public function test_an_automatic_free_delivery_offer(): void
    {
        Setting::set(['free_delivery_over' => 0]);
        $delivery = app(DeliveryService::class);
        $this->assertFalse($delivery->freeDeliveryApplies(400));

        Voucher::factory()->create(['code' => 'FREESHIP', 'type' => 'fixed', 'amount' => 0, 'min_order' => 300, 'is_automatic' => true, 'free_delivery' => true]);
        $this->assertTrue($delivery->freeDeliveryApplies(400));
        $this->assertFalse($delivery->freeDeliveryApplies(200));

        // A free-delivery-only offer does not take the voucher's place
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertNull(app(DiscountService::class)->calculateVoucherDiscount($this->cart($user, 400))['voucher']);
    }

    public function test_admins_make_print_and_export_a_batch_of_cards(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($admin)->post(route('admin.vouchers.batch'), ['name' => 'EID 26', 'count' => 3, 'type' => 'fixed', 'amount' => 25, 'valid_until' => now()->addMonth()->toDateString()])->assertSessionHasErrors('name');
        $this->post(route('admin.vouchers.batch'), ['name' => 'EID26', 'count' => 3, 'type' => 'fixed', 'amount' => 25, 'valid_until' => now()->addMonth()->toDateString()])->assertSessionHas('success');

        $cards = Voucher::whereNotNull('batch')->get();
        $this->assertCount(3, $cards);
        $this->assertTrue($cards->every(fn ($c) => $c->max_uses === 1 && str_starts_with($c->code, 'EID26-')));
        $batch = $cards->first()->batch;

        $this->get(route('admin.vouchers.index'))->assertSee($batch)->assertDontSee($cards->first()->code);
        $this->get(route('admin.vouchers.batch.print', $batch))->assertOk()->assertSee($cards->first()->code);
        $csv = $this->get(route('admin.vouchers.batch.export', $batch))->assertOk()->streamedContent();
        $this->assertSame(4, count(array_filter(explode("\n", $csv))));
    }
}

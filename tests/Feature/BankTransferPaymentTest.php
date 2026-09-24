<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->customer = User::factory()->create();
    }

    protected function enableBankTransfer(): void
    {
        Setting::set(['bank_account_name' => 'Iruali Pvt Ltd', 'bank_account_number' => '7730000123456']);
    }

    protected function placeOrder(string $method)
    {
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 200]);

        return $this->actingAs($this->customer)->post('/orders', [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '20026', 'shipping_country' => 'Maldives',
            'payment_method' => $method, 'agree_terms' => '1',
        ]);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_bank_transfer_is_only_offered_once_bank_details_are_set(): void
    {
        $this->placeOrder('bank_transfer')->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());

        $this->enableBankTransfer();
        $this->placeOrder('bank_transfer')->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);

        $this->get(route('orders.show', $order))->assertOk()->assertSee('7730000123456')->assertSee($order->order_number);
    }

    public function test_customer_uploads_slip_and_admin_confirms(): void
    {
        $this->enableBankTransfer();
        $this->placeOrder('bank_transfer');
        $order = Order::firstOrFail();

        $this->post(route('orders.payment-slip.store', $order), [
            'payment_slip' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('submitted', $order->payment_status);
        Storage::disk('local')->assertExists($order->payment_slip);

        // The customer and admins can open the slip; nobody else can
        $this->get(route('orders.payment-slip.show', $order))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('orders.payment-slip.show', $order))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('View transfer slip');
        $this->actingAs($admin)->get(route('orders.payment-slip.show', $order))->assertOk();

        $this->actingAs($admin)->post(route('admin.orders.payment', $order), ['action' => 'confirm'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_rejected_slip_can_be_uploaded_again(): void
    {
        $this->enableBankTransfer();
        $this->placeOrder('bank_transfer');
        $order = Order::firstOrFail();
        $this->post(route('orders.payment-slip.store', $order), ['payment_slip' => UploadedFile::fake()->image('slip.jpg')]);

        $this->actingAs($this->admin())->post(route('admin.orders.payment', $order), ['action' => 'reject']);
        $this->assertSame('rejected', $order->fresh()->payment_status);

        $this->actingAs($this->customer)
            ->post(route('orders.payment-slip.store', $order), ['payment_slip' => UploadedFile::fake()->create('slip.pdf', 200, 'application/pdf')])
            ->assertRedirect();
        $this->assertSame('submitted', $order->fresh()->payment_status);
    }

    public function test_only_images_and_pdfs_are_accepted_and_only_by_the_owner(): void
    {
        $this->enableBankTransfer();
        $this->placeOrder('bank_transfer');
        $order = Order::firstOrFail();

        $this->post(route('orders.payment-slip.store', $order), ['payment_slip' => UploadedFile::fake()->create('slip.exe', 10)])
            ->assertSessionHasErrors('payment_slip');

        $this->actingAs(User::factory()->create())
            ->post(route('orders.payment-slip.store', $order), ['payment_slip' => UploadedFile::fake()->image('slip.jpg')])
            ->assertForbidden();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_only_admins_can_confirm_payment(): void
    {
        $this->placeOrder('cod');
        $order = Order::firstOrFail();

        $this->actingAs($this->customer)->post(route('admin.orders.payment', $order), ['action' => 'confirm'])->assertForbidden();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->actingAs($this->admin())->post(route('admin.orders.payment', $order), ['action' => 'confirm']);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }
}

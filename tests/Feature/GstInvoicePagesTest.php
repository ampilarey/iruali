<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * The printable invoices (each shop's tax invoice or receipt, iruali's commission invoice) and who
 * may open them; iruali's receipt with GST lines.
 */
class GstInvoicePagesTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected User $customer;

    protected User $crafts;

    protected User $reef;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 81, 'default_commission_rate' => 10]);

        $this->customer = User::factory()->create(['name' => 'Aishath Shifa']);
        $this->crafts = $this->shop('Island Crafts', '1012345GST501');
        $this->reef = $this->shop('Reefline Marine');
    }

    protected function paidOrder(): Order
    {
        return $this->pay($this->placeOrder($this->customer, [[$this->crafts, 540, 2], [$this->reef, 100]]));
    }

    protected function part(Order $order, User $shop): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->sole();
    }

    public function test_the_customer_opens_each_shops_invoice_and_nobody_else_can(): void
    {
        $order = $this->paidOrder();
        $crafts = $this->part($order, $this->crafts);
        $reef = $this->part($order, $this->reef);

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('Invoices from the shops')->assertSee('Tax invoice · Island Crafts')->assertSee('Receipt · Reefline Marine')
            ->assertSee(route('orders.invoice', [$order, $crafts]), false)->assertSee($crafts->invoice_number);

        $this->actingAs($this->customer)->get(route('orders.invoice', [$order, $crafts]))->assertOk()
            ->assertSee('data-invoice="tax"', false)->assertSee('Tax invoice')->assertSee($crafts->invoice_number)
            ->assertSee('Island Crafts Pvt Ltd')->assertSee('H. Coral View, Malé')->assertSee('1012345GST501')
            ->assertSee('Aishath Shifa')->assertSee('GST breakdown')->assertSee('MVR 1,080.00')->assertSee('MVR 1,000.00')->assertSee('MVR 80.00')
            ->assertDontSee('Reefline');
        $this->actingAs($this->customer)->get(route('orders.invoice', [$order, $reef]))->assertOk()
            ->assertSee('data-invoice="receipt"', false)->assertSee('Receipt no.')->assertDontSee('GST')->assertSee('MVR 100.00');

        // Someone else's order, a part of another order, not signed in
        $this->actingAs(User::factory()->create())->get(route('orders.invoice', [$order, $crafts]))->assertForbidden();
        $other = $this->paidOrder();
        $this->actingAs($this->customer)->get(route('orders.invoice', [$order, $this->part($other, $this->crafts)]))->assertNotFound();
        auth()->logout();
        $this->get(route('orders.invoice', [$order, $crafts]))->assertRedirect(route('login'));
    }

    public function test_unpaid_orders_have_no_invoice(): void
    {
        $order = $this->placeOrder($this->customer, [[$this->crafts, 540]]);
        $part = $this->part($order, $this->crafts);

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertDontSee('Invoices from the shops');
        $this->actingAs($this->customer)->get(route('orders.invoice', [$order, $part]))->assertNotFound();
        $this->actingAs($this->crafts)->get(route('seller.orders.invoice', $order))->assertNotFound();
        $this->actingAs($this->staff('admin'))->get(route('admin.orders.invoice', [$order, $part]))->assertNotFound();
        $this->assertNull($part->fresh()->invoice_number);
    }

    public function test_a_guest_opens_invoices_only_through_the_signed_link(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['user_id' => null, 'guest_email' => 'guest@example.com', 'guest_name' => 'Guest Buyer', 'guest_token' => str_repeat('a', 40)])->save();
        $part = $this->part($order, $this->crafts);

        $link = URL::signedRoute('guest.orders.invoice', ['order' => $order->id, 'token' => $order->guest_token, 'part' => $part->id]);
        $this->get($link)->assertOk()->assertSee('Tax invoice')->assertSee('Guest Buyer');
        $this->get($order->guestUrl())->assertOk()->assertSee('Invoices from the shops')->assertSee('/invoices/'.$part->id, false);

        $this->get(URL::signedRoute('guest.orders.invoice', ['order' => $order->id, 'token' => str_repeat('b', 40), 'part' => $part->id]))->assertForbidden();
        $this->get(route('guest.orders.invoice', ['order' => $order->id, 'token' => $order->guest_token, 'part' => $part->id]))->assertForbidden(); // unsigned
    }

    public function test_a_shop_opens_only_its_own_invoice(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->crafts)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('data-shop-invoice', false)->assertSee($this->part($order, $this->crafts)->invoice_number)->assertSee('MVR 80.00');
        $this->actingAs($this->crafts)->get(route('seller.orders.invoice', $order))->assertOk()
            ->assertSee($this->part($order, $this->crafts)->invoice_number)->assertDontSee('Reefline');
        $this->actingAs($this->reef)->get(route('seller.orders.invoice', $order))->assertOk()
            ->assertSee('data-invoice="receipt"', false)->assertSee($this->part($order, $this->reef)->invoice_number);

        $this->actingAs($this->shop('Other Shop'))->get(route('seller.orders.invoice', $order))->assertNotFound();
        $this->actingAs($this->customer)->get(route('seller.orders.invoice', $order))->assertForbidden();
    }

    public function test_staff_open_invoices_by_role(): void
    {
        $order = $this->paidOrder();
        $part = $this->part($order, $this->crafts);

        foreach (['admin', 'finance', 'support'] as $role) {
            $this->actingAs($this->staff($role))->get(route('admin.orders.invoice', [$order, $part]))->assertOk()->assertSee($part->invoice_number);
        }
        $this->actingAs($this->staff('admin'))->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('data-order-tax', false)->assertSee(route('admin.orders.invoice', [$order, $part]), false);
        $this->actingAs($this->staff('admin'))->get(route('admin.orders.invoice', [$order, $this->part($this->paidOrder(), $this->crafts)]))->assertNotFound();
        $this->actingAs($this->customer)->get(route('admin.orders.invoice', [$order, $part]))->assertForbidden();
        $this->actingAs($this->crafts)->get(route('admin.orders.invoice', [$order, $part]))->assertForbidden();
    }

    public function test_commission_invoices_for_payouts(): void
    {
        $this->registerIruali(8);
        $order = $this->paidOrder();
        $this->actingAs($this->staff('admin'));
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }

        $payout = app(PayoutService::class)->createPayout($this->crafts, null, 'TRF-1', null, $this->staff('admin'));
        $this->assertSame('INV-C-000001', $payout->invoice_number);
        $this->assertSame('Island Crafts Pvt Ltd', $payout->invoice_details['name']);
        $this->assertSame('1012345GST501', $payout->invoice_details['tin']);

        // Commission 108.00 (10% of 1,080), with 8.00 GST in it
        $this->actingAs($this->crafts)->get(route('seller.payouts.invoice', $payout))->assertOk()
            ->assertSee('data-commission-invoice="tax"', false)->assertSee('INV-C-000001')->assertSee('1000001GST501')
            ->assertSee('Island Crafts Pvt Ltd')->assertSee('MVR 108.00')->assertSee('MVR 8.00')->assertSee('MVR 972.00');
        $this->actingAs($this->crafts)->get(route('seller.earnings'))->assertOk()->assertSee(route('seller.payouts.invoice', $payout), false);
        $this->actingAs($this->reef)->get(route('seller.payouts.invoice', $payout))->assertForbidden();

        foreach (['admin', 'finance'] as $role) {
            $this->actingAs($this->staff($role))->get(route('admin.payouts.invoice', $payout))->assertOk()->assertSee('INV-C-000001');
        }
        $this->actingAs($this->staff('admin'))->get(route('admin.payouts.show', $payout))->assertOk()->assertSee(route('admin.payouts.invoice', $payout), false);
        $this->actingAs($this->staff('admin'))->get(route('admin.payouts'))->assertOk()->assertSee('INV-C-000001');
        $this->actingAs($this->staff('support'))->get(route('admin.payouts.invoice', $payout))->assertForbidden();
    }

    public function test_a_payout_in_a_batch_is_invoiced_when_the_batch_is_paid(): void
    {
        $order = $this->paidOrder();
        $admin = $this->staff('admin');
        $this->actingAs($admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }

        $payouts = app(PayoutService::class);
        $batch = $payouts->createBatch([$this->crafts->id, $this->reef->id], $admin);
        $pending = SellerPayout::where('seller_id', $this->crafts->id)->sole();
        $this->assertNull($pending->invoice_number);
        $this->actingAs($this->crafts)->get(route('seller.payouts.invoice', $pending))->assertOk()->assertSee('Not issued yet');
        $this->actingAs($this->crafts)->get(route('seller.earnings'))->assertOk()->assertDontSee(route('seller.payouts.invoice', $pending), false);

        $payouts->markBatchPaid($batch, 'BML-BULK-1', now(), $admin);
        $this->assertSame(['INV-C-000001', 'INV-C-000002'], SellerPayout::orderBy('id')->pluck('invoice_number')->all());

        // Not registered: a plain invoice, no GST
        $this->actingAs($this->crafts)->get(route('seller.payouts.invoice', $pending))->assertOk()
            ->assertSee('data-commission-invoice="plain"', false)->assertDontSee('incl. GST')->assertDontSee('Not issued yet');
    }

    public function test_iruali_receipt_shows_the_gst_in_the_delivery_fee_when_iruali_is_registered(): void
    {
        $this->registerIruali(8);
        $order = $this->paidOrder();

        $this->actingAs($this->customer)->get(route('orders.receipt', $order))->assertOk()
            ->assertSee('data-receipt-gst', false)->assertSee('GST included in delivery (8%)')->assertSee('MVR 6.00')->assertSee('1000001GST501');
    }

    public function test_invoices_show_dhivehi_labels_on_dhivehi_pages(): void
    {
        $order = $this->paidOrder();
        $part = $this->part($order, $this->crafts);

        $this->actingAs($this->customer)->withSession(['locale' => 'dv'])->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('dir="ltr"', false)->assertSee('Tax invoice')->assertSee('ޓެކްސް އިންވޮއިސް')->assertSee('lang="dv"', false);
        $this->actingAs($this->customer)->withSession(['locale' => 'en'])->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('Tax invoice')->assertDontSee('ޓެކްސް އިންވޮއިސް');
    }

    public function test_every_invoice_label_has_a_dhivehi_translation(): void
    {
        $dv = json_decode(file_get_contents(lang_path('dv.json')), true);
        // T::label('…'), and T::label($condition ? '…' : '…')
        $pattern = '/T::label\((?:\$\w+(?:\[\'\w+\'\])? \? )?\'((?:[^\'\\\\]|\\\\.)*)\'(?: : \'((?:[^\'\\\\]|\\\\.)*)\')?/';
        $labels = ['Tax invoice', 'Receipt', 'Invoice']; // the titles, picked in a variable
        foreach (glob(resource_path('views/tax/*.blade.php')) as $file) {
            preg_match_all($pattern, file_get_contents($file), $matches);
            $labels = array_merge($labels, $matches[1], array_filter($matches[2]));
        }
        $labels = array_values(array_unique(array_map('stripslashes', $labels)));

        $this->assertGreaterThan(40, count($labels));
        $this->assertSame([], array_values(array_diff($labels, array_keys($dv))));
    }
}

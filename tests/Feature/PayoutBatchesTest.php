<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\User;
use App\Notifications\PayoutPaid;
use App\Services\PayoutService;
use App\Support\BankFileFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayoutBatchesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shopA;

    protected User $shopB;

    protected User $shopNoBank;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->shopA = $this->seller('Island Crafts', '7730000123456');
        $this->shopB = $this->seller('Reefline Marine', '7770000654321');
        $this->shopNoBank = $this->seller('Atoll Spices', null);
    }

    protected function seller(string $name, ?string $account): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        if ($account) {
            $user->bankAccount()->create(['bank' => 'bml', 'account_name' => $name.' Pvt Ltd', 'account_number' => $account]);
        }

        return $user;
    }

    /**
     * A delivered, paid part: the shop earns $price minus 10% commission.
     */
    protected function payablePart(User $seller, float $price): SellerOrder
    {
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => $price]);
        $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => $price]);

        $part = SellerOrder::where('order_id', $order->id)->where('seller_id', $seller->id)->firstOrFail();
        $commission = round($price * 0.10, 2);
        $part->update(['status' => 'delivered', 'delivered_at' => now(), 'commission_rate' => 10, 'commission_amount' => $commission, 'seller_earnings' => $price - $commission]);

        return $part;
    }

    public function test_batch_totals_cover_every_payable_part_and_open_adjustments(): void
    {
        $this->payablePart($this->shopA, 1000);
        $this->payablePart($this->shopA, 500);
        $this->payablePart($this->shopB, 200);
        $this->payablePart($this->shopNoBank, 300);
        SellerAdjustment::create(['seller_id' => $this->shopA->id, 'amount' => -50, 'reason' => 'Return on order X']);

        $this->actingAs($this->admin)->get(route('admin.payout-batches.create'))->assertOk()
            ->assertSee('Island Crafts')->assertSee('MVR 1,300.00')->assertSee('Atoll Spices')->assertSee('has not added a bank account');

        $this->post(route('admin.payout-batches.store'), ['sellers' => [$this->shopA->id, $this->shopB->id, $this->shopNoBank->id]])->assertRedirect();

        $batch = PayoutBatch::sole();
        $this->assertSame('draft', $batch->status);
        $this->assertSame(2, $batch->count, 'The shop without bank details is skipped');
        $this->assertEquals(1300 + 180, $batch->total);
        $this->assertMatchesRegularExpression('/^PB-\d{4}-0001$/', $batch->reference);

        $payouts = $batch->payouts()->orderBy('seller_id')->get();
        $this->assertEquals([1300.0, 180.0], $payouts->map(fn ($p) => (float) $p->amount)->all());
        $this->assertSame(['pending', 'pending'], $payouts->pluck('status')->all());
        $this->assertSame(2, SellerOrder::where('payout_id', $payouts[0]->id)->count());
        $this->assertSame($payouts[0]->id, SellerAdjustment::sole()->payout_id);

        $balances = app(PayoutService::class)->balances($this->shopA);
        $this->assertEquals(['available' => 0.0, 'processing' => 1300.0, 'paid' => 0.0], array_intersect_key($balances, ['available' => 1, 'processing' => 1, 'paid' => 1]));

        $this->get(route('admin.payout-batches.show', $batch))->assertOk()->assertSee('7730000123456')->assertSee('Island Crafts Pvt Ltd')->assertSee($batch->reference.'/'.$payouts[0]->id);
    }

    public function test_earnings_cannot_be_in_two_batches(): void
    {
        $part = $this->payablePart($this->shopA, 100);
        $payouts = app(PayoutService::class);

        $first = $payouts->createBatch([$this->shopA->id], $this->admin);
        $this->assertNotNull($first);

        // Nothing is left to pay, so a second batch is refused and the per-shop payout too
        $this->assertNull($payouts->createBatch([$this->shopA->id], $this->admin));
        $this->assertNull($payouts->createPayout($this->shopA, [$part->id], 'TRF', null, $this->admin));
        $this->assertSame(1, PayoutBatch::count());
        $this->assertSame(1, SellerPayout::count());
        $this->assertSame($first->payouts()->first()->id, $part->fresh()->payout_id);

        // Cancelling the draft releases the earnings; the next batch gets a new number and picks them up
        $this->assertTrue($payouts->cancelBatch($first));
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertNull($part->fresh()->payout_id);
        $this->assertSame(0, SellerPayout::count());

        $second = $payouts->createBatch([$this->shopA->id], $this->admin);
        $this->assertNotSame($first->reference, $second->reference);
        $this->assertEquals(90, $second->total);
        $this->assertFalse($payouts->cancelBatch($first), 'A cancelled batch stays cancelled');
    }

    public function test_bank_file_matches_the_bml_bulk_format_byte_for_byte(): void
    {
        $this->payablePart($this->shopA, 1250);
        $this->payablePart($this->shopB, 99.5);
        $this->shopB->bankAccount->update(['account_name' => 'Reefline, "Marine"']);

        $batch = app(PayoutService::class)->createBatch([$this->shopB->id, $this->shopA->id], $this->admin);
        $a = $batch->payouts()->where('seller_id', $this->shopA->id)->first();
        $b = $batch->payouts()->where('seller_id', $this->shopB->id)->first();

        $response = $this->actingAs($this->admin)->get(route('admin.payout-batches.file', $batch))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="bml-bulk-'.$batch->reference.'.csv"');

        $expected = "Beneficiary Account Number,Beneficiary Name,Amount,Remarks\r\n"
            ."7730000123456,Island Crafts Pvt Ltd,1125.00,{$batch->reference}/{$a->id}\r\n"
            ."7770000654321,\"Reefline, \"\"Marine\"\"\",89.55,{$batch->reference}/{$b->id}\r\n";
        $this->assertSame($expected, $response->getContent());
        $this->assertSame(BankFileFormat::COLUMNS, ['Beneficiary Account Number', 'Beneficiary Name', 'Amount', 'Remarks']);
        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $response->getContent(), 'No byte-order mark');

        $this->assertSame('exported', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->exported_at);

        // An exported batch can't be cancelled (the file may already be at the bank), only marked paid
        $this->post(route('admin.payout-batches.cancel', $batch))->assertSessionHas('error');
        $this->assertSame('exported', $batch->fresh()->status);
    }

    public function test_marking_a_batch_paid_pays_every_payout_once_and_tells_the_shops(): void
    {
        $part = $this->payablePart($this->shopA, 100);
        $this->payablePart($this->shopB, 200);
        $batch = app(PayoutService::class)->createBatch([$this->shopA->id, $this->shopB->id], $this->admin);

        $this->actingAs($this->admin)->post(route('admin.payout-batches.paid', $batch), ['bank_reference' => 'BML-BULK-9981', 'paid_on' => '2026-10-01'])
            ->assertRedirect()->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('paid', $batch->status);
        $this->assertSame('BML-BULK-9981', $batch->bank_reference);
        $this->assertSame('2026-10-01', $batch->paid_at->toDateString());
        $this->assertSame(['paid', 'paid'], $batch->payouts()->pluck('status')->all());
        $this->assertSame('paid_out', $part->fresh()->earningsState());

        $payoutA = $batch->payouts()->where('seller_id', $this->shopA->id)->first();
        $this->assertSame('2026-10-01', $payoutA->paid_at->toDateString());
        $this->assertSame($batch->reference.'/'.$payoutA->id, $payoutA->reference);

        Notification::assertSentTo($this->shopA, PayoutPaid::class, fn ($n) => (float) $n->payout->amount === 90.0);
        Notification::assertSentTo($this->shopB, PayoutPaid::class);
        Notification::assertNotSentTo($this->shopNoBank, PayoutPaid::class);
        $this->assertContains(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements(PayoutPaid::class));

        $balances = app(PayoutService::class)->balances($this->shopA);
        $this->assertEquals(['processing' => 0.0, 'paid' => 90.0], array_intersect_key($balances, ['processing' => 1, 'paid' => 1]));

        // Paying again does nothing
        $this->post(route('admin.payout-batches.paid', $batch), ['bank_reference' => 'again', 'paid_on' => '2026-10-02'])->assertSessionHas('error');
        $this->assertSame('BML-BULK-9981', $batch->fresh()->bank_reference);

        $this->actingAs($this->shopA)->get(route('seller.earnings'))->assertOk()
            ->assertSee($batch->reference)->assertSee('BML-BULK-9981')->assertSee('01 Oct 2026')->assertSee('MVR 90.00');
    }

    public function test_shops_see_a_drafted_payout_as_on_its_way(): void
    {
        $this->payablePart($this->shopA, 100);
        $batch = app(PayoutService::class)->createBatch([$this->shopA->id], $this->admin);

        $this->actingAs($this->shopA)->get(route('seller.earnings'))->assertOk()
            ->assertSee('Payout on its way')->assertSee($batch->reference)->assertSee('MVR 90.00 is on its way');
    }

    public function test_only_admins_manage_batches(): void
    {
        $this->payablePart($this->shopA, 100);
        $batch = app(PayoutService::class)->createBatch([$this->shopA->id], $this->admin);

        foreach ([$this->shopA, User::factory()->create()] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.payout-batches.index'))->assertForbidden();
            $this->get(route('admin.payout-batches.create'))->assertForbidden();
            $this->post(route('admin.payout-batches.store'), ['sellers' => [$this->shopA->id]])->assertForbidden();
            $this->get(route('admin.payout-batches.show', $batch))->assertForbidden();
            $this->get(route('admin.payout-batches.file', $batch))->assertForbidden();
            $this->post(route('admin.payout-batches.paid', $batch), ['bank_reference' => 'x', 'paid_on' => '2026-10-01'])->assertForbidden();
            $this->post(route('admin.payout-batches.cancel', $batch))->assertForbidden();
        }
        $this->assertSame('draft', $batch->fresh()->status);

        auth()->logout();
        $this->get(route('admin.payout-batches.index'))->assertRedirect('/login');

        $this->actingAs($this->admin)->get(route('admin.payout-batches.index'))->assertOk()->assertSee($batch->reference);
        $this->get(route('admin.payouts'))->assertOk()->assertSee('Payout batches');
    }
}

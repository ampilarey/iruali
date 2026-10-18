<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin → Finance (profit and loss, expenses, recurring ones, Xero files) and Admin → Exports.
 */
class FinanceAndExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function paidOrderWithCommission(float $commission, float $delivery): Order
    {
        $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid', 'paid_at' => now(), 'shipping_amount' => $delivery, 'total_amount' => 500 + $delivery]);
        (new SellerOrder)->forceFill(['order_id' => $order->id, 'seller_id' => User::factory()->create()->id, 'status' => 'delivered', 'subtotal' => 500, 'commission_rate' => 10, 'commission_amount' => $commission, 'seller_earnings' => 500 - $commission])->save();
        \App\Models\OrderItem::create(['order_id' => $order->id, 'product_id' => \App\Models\Product::factory()->create()->id, 'quantity' => 1, 'price' => 500]);

        return $order;
    }

    public function test_the_monthly_profit_and_loss(): void
    {
        $this->paidOrderWithCommission(50, 30);
        $order = $this->paidOrderWithCommission(70, 0);
        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'bml', 'local_id' => 'L1', 'amount' => 100000, 'currency' => 'MVR', 'state' => 'CONFIRMED', 'confirmed_at' => now()]);
        Setting::set(['card_fee_percent' => 2]);
        Expense::create(['spent_on' => today(), 'category' => 'hosting', 'description' => 'Server', 'amount' => 100, 'gst_amount' => 0]);

        $pnl = app(FinanceService::class)->profitAndLoss(now()->format('Y-m'));
        $commission = (float) SellerOrder::sum('commission_amount'); // ours plus any the order factory adds
        $this->assertEqualsWithDelta($commission, $pnl['income']['commission'], 0.01);
        $this->assertGreaterThanOrEqual(120, $pnl['income']['commission']);
        $this->assertEqualsWithDelta(30, $pnl['income']['delivery'], 0.01);
        $this->assertEqualsWithDelta(20, $pnl['card_fees'], 0.01); // 2% of MVR 1,000
        $this->assertEqualsWithDelta($commission + 30 - 120, $pnl['profit'], 0.01);

        $this->actingAs($this->staff('finance'))->get(route('admin.finance'))->assertOk()->assertSee('data-pnl', false)->assertSee('Server');
        $this->put(route('admin.finance.settings'), ['card_fee_percent' => 1, 'xero_sales_account' => '200', 'xero_expense_account' => '400', 'xero_tax_sales' => 'x', 'xero_tax_purchases' => 'y'])->assertForbidden();
    }

    public function test_expenses_with_receipts_recurring_and_xero_bills(): void
    {
        Storage::fake('local');
        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.finance.expenses.store'), [
            'spent_on' => now()->startOfMonth()->addDays(4)->toDateString(), 'category' => 'sms', 'description' => 'Dhiraagu SMS bundle', 'supplier' => 'Dhiraagu',
            'amount' => 1080, 'gst_amount' => 80, 'recurring' => 1, 'receipt' => UploadedFile::fake()->create('bill.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $expense = Expense::sole();
        Storage::disk('local')->assertExists($expense->receipt_path);
        $this->get(route('admin.finance.expenses.receipt', $expense))->assertOk();

        $csv = $this->get(route('admin.finance.xero.bills', ['month' => now()->format('Y-m')]))->streamedContent();
        $this->assertStringContainsString('Dhiraagu SMS bundle', $csv);
        $this->assertStringContainsString('GST on Expenses', $csv);

        // Next month the monthly expense is copied, once
        Carbon::setTestNow(now()->addMonth()->startOfMonth());
        $this->artisan('finance:recurring-expenses')->expectsOutputToContain('Copied 1')->assertSuccessful();
        $this->artisan('finance:recurring-expenses')->expectsOutputToContain('Copied 0');
        $this->assertSame(5, Expense::latest('id')->first()->spent_on->day);
        Carbon::setTestNow();
    }

    public function test_csv_exports_for_a_date_range(): void
    {
        $order = $this->paidOrderWithCommission(50, 30);
        $order->forceFill(['voucher_code' => 'EID26', 'voucher_discount' => 25, 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu'])->save();
        $finance = $this->staff('finance');

        $this->actingAs($finance)->get(route('admin.exports'))->assertOk()->assertSee('data-exports', false);
        foreach (array_keys(\App\Http\Controllers\Admin\ExportController::KINDS) as $kind) {
            $this->get(route('admin.exports.download', ['kind' => $kind, 'from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()]))->assertOk();
        }
        $this->assertStringContainsString($order->order_number, $this->get(route('admin.exports.download', 'orders'))->streamedContent());
        $this->assertStringContainsString('EID26', $this->get(route('admin.exports.download', 'vouchers'))->streamedContent());
        $this->assertStringContainsString('Hithadhoo, Addu', $this->get(route('admin.exports.download', 'sales_island'))->streamedContent());
        $this->get(route('admin.exports.download', 'nonsense'))->assertNotFound();

        $this->actingAs($this->staff('catalogue'))->get(route('admin.exports'))->assertForbidden();
    }
}

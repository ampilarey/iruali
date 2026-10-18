<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\GstPeriod;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin → Settlements (BML statement matched to orders) and GST tools (ledger, lock, reminder),
 * and spreadsheet-safe CSV text.
 */
class SettlementsAndGstToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function statement(string $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('statement.csv', "BML account statement\nValue Date,Narrative,Reference,Debit,Credit\n".$rows);
    }

    public function test_a_statement_is_imported_and_matched_to_orders(): void
    {
        $transfer = Order::factory()->create(['payment_method' => 'transfer', 'payment_status' => 'pending', 'status' => 'pending', 'total_amount' => 450]);
        $byAmount = Order::factory()->create(['payment_method' => 'transfer', 'payment_status' => 'pending', 'status' => 'pending', 'total_amount' => 777.50]);
        $card = Order::factory()->create(['payment_method' => 'bml', 'payment_status' => 'paid', 'status' => 'processing']);
        PaymentTransaction::create(['order_id' => $card->id, 'gateway' => 'bml', 'local_id' => 'L-CARD-1', 'transaction_id' => 'TXN98765', 'amount' => 30000, 'currency' => 'MVR', 'state' => 'CONFIRMED', 'confirmed_at' => now()]);

        $csv = $this->statement(implode("\n", [
            '15/10/2026,IB transfer from Mariyam '.$transfer->order_number.',,,"450.00"',
            '15/10/2026,Card settlement TXN98765,,,300.00',
            '16/10/2026,Transfer from A. Ali,REF77,,777.50',
            '16/10/2026,Bank charges,,25.00,',
            '17/10/2026,Unknown deposit,,,1234.00',
        ]));

        $finance = $this->staff('finance');
        $this->actingAs($finance)->post(route('admin.settlements.import'), ['statement' => $csv])->assertSessionHas('success');
        $this->assertSame(4, BankStatementLine::count()); // the debit is left out
        $this->assertSame(['transfer', 'card', 'amount'], BankStatementLine::where('status', 'matched')->orderBy('id')->pluck('match_kind')->all());

        // Importing the same statement again adds nothing
        $this->post(route('admin.settlements.import'), ['statement' => $csv])->assertSessionHas('success');
        $this->assertSame(4, BankStatementLine::count());

        $line = BankStatementLine::where('order_id', $transfer->id)->sole();
        $this->post(route('admin.settlements.confirm', $line))->assertSessionHas('success');
        $this->assertSame('paid', $transfer->fresh()->payment_status);

        $unknown = BankStatementLine::where('status', 'unmatched')->sole();
        $this->post(route('admin.settlements.match', $unknown), ['order_number' => 'ORD-NOPE'])->assertSessionHas('error');
        $this->post(route('admin.settlements.ignore', $unknown));
        $this->assertSame('ignored', $unknown->fresh()->status);
        $this->get(route('admin.settlements', ['status' => 'all']))->assertOk()->assertSee($byAmount->order_number);
    }

    public function test_a_file_without_a_heading_row_is_refused(): void
    {
        $this->actingAs($this->staff('admin'))->post(route('admin.settlements.import'), ['statement' => UploadedFile::fake()->createWithContent('x.csv', "a,b\n1,2\n")])
            ->assertSessionHas('error');
    }

    public function test_a_filed_gst_month_is_locked_with_its_figures_and_the_reminder_stops(): void
    {
        Mail::fake();
        config(['mail.alerts_to' => 'owner@iruali.mv']);
        $month = now()->subMonthNoOverflow()->format('Y-m');
        $finance = $this->staff('finance');

        $this->artisan('gst:filing-reminder', ['--force' => true])->expectsOutputToContain('Reminder sent');
        $this->actingAs($finance)->get(route('admin.tax.report', ['month' => $month]))->assertOk()->assertSee('data-gst-lock', false);
        $this->get(route('admin.tax.report', ['month' => $month, 'export' => 'ledger']))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->post(route('admin.tax.lock'), ['month' => $month, 'filing_reference' => 'MIRA-123'])->assertSessionHas('success');
        $this->assertSame('MIRA-123', GstPeriod::sole()->filing_reference);
        $this->get(route('admin.tax.report', ['month' => $month]))->assertSee('data-gst-locked', false)->assertSee('No changes since filing.');
        $this->artisan('gst:filing-reminder', ['--force' => true])->doesntExpectOutputToContain('Reminder sent');

        $this->delete(route('admin.tax.unlock', $month))->assertForbidden(); // admins only
    }

    public function test_csv_text_that_looks_like_a_formula_is_neutralised(): void
    {
        $this->assertSame(["'=HYPERLINK(1)", 'Aisha', '-12.50', 12], \App\Support\Csv::row(['=HYPERLINK(1)', 'Aisha', '-12.50', 12]));
    }
}

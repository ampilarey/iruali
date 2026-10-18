<?php

namespace Tests\Feature;

use App\Mail\RefundsSummary;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A second person approves big refunds, the morning refunds summary, and receipt PDFs / emails.
 */
class RefundApprovalAndReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function refundDue(float $amount): Order
    {
        return tap(Order::factory()->create(['status' => 'cancelled', 'payment_status' => 'paid', 'payment_method' => 'transfer']))
            ->forceFill(['refund_status' => 'due', 'refund_amount' => $amount, 'refund_reason' => 'Cancelled after payment'])->save() ? Order::latest('id')->first() : null;
    }

    public function test_big_refunds_need_a_second_person(): void
    {
        Setting::set(['refund_approval_over' => 1000]);
        $order = $this->refundDue(2500);
        $first = $this->staff('finance');
        $second = $this->staff('finance');

        $this->actingAs($first)->get(route('admin.orders.show', $order))->assertSee('data-refund-approval', false);
        $this->post(route('admin.orders.refunded', $order), ['refund_reference' => 'TRX1'])->assertSessionHas('error');
        $this->assertSame('due', $order->fresh()->refund_status);

        $this->post(route('admin.orders.refund-approve', $order))->assertSessionHas('success');
        $this->post(route('admin.orders.refunded', $order), ['refund_reference' => 'TRX1'])->assertSessionHas('error', 'You approved this refund, so someone else has to record it.');

        $this->actingAs($second)->post(route('admin.orders.refunded', $order), ['refund_reference' => 'TRX1'])->assertSessionHas('success');
        $this->assertSame('refunded', $order->fresh()->refund_status);
        $this->assertTrue(AuditLog::where('action', 'refund.approved')->exists());

        // Small ones go straight through
        $small = $this->refundDue(200);
        $this->post(route('admin.orders.refunded', $small), ['refund_reference' => 'TRX2'])->assertSessionHas('success');
    }

    public function test_the_morning_summary_lists_yesterdays_refunds_and_what_is_due(): void
    {
        Mail::fake();
        config(['mail.alerts_to' => 'owner@iruali.mv']);
        $this->artisan('refunds:daily-summary')->expectsOutputToContain('Nothing to report');

        $done = $this->refundDue(300);
        $done->forceFill(['refund_status' => 'refunded', 'refunded_at' => now()->subDay()->setTime(10, 0), 'refund_reference' => 'TRX9'])->save();
        Setting::set(['refund_approval_over' => 1000]);
        $this->refundDue(1500);

        $this->artisan('refunds:daily-summary')->expectsOutputToContain('Summary sent')->assertSuccessful();
        Mail::assertQueued(RefundsSummary::class, fn ($m) => count($m->summary['refunded']) === 1 && $m->summary['due_count'] === 1 && $m->summary['waiting_approval'] === 1);
    }

    public function test_customers_download_and_email_their_receipt(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'payment_status' => 'paid']);

        $this->actingAs($customer)->get(route('orders.receipt', $order))->assertOk()->assertSee('data-receipt-pdf', false)->assertSee('data-receipt-email', false);
        $pdf = $this->get(route('orders.receipt.pdf', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->post(route('orders.receipt.email', $order))->assertSessionHas('success');
        Notification::assertSentTo($customer, OrderReceipt::class);
        $mail = (new OrderReceipt($order))->toMail($customer);
        $this->assertCount(1, $mail->rawAttachments);

        $this->actingAs(User::factory()->create())->get(route('orders.receipt.pdf', $order))->assertForbidden();
    }

    public function test_guests_use_their_signed_links(): void
    {
        Notification::fake();
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'g@example.com', 'guest_token' => str_repeat('b', 40)]);
        $this->get($order->guestUrl('receipt-pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post($order->guestUrl('receipt-email'))->assertRedirect();
        Notification::assertSentOnDemand(OrderReceipt::class);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_notification_is_queued_and_waits_for_the_transaction(): void
    {
        foreach (glob(app_path('Notifications/*.php')) as $file) {
            $class = 'App\\Notifications\\'.basename($file, '.php');
            $this->assertTrue(is_subclass_of($class, ShouldQueue::class), "$class must implement ShouldQueue");
            $this->assertTrue((new \ReflectionClass($class))->hasProperty('afterCommit'), "$class must use Queueable");
        }
    }

    public function test_notifications_go_through_the_queue(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['status' => 'processing']);

        $order->user->notify(new OrderStatusChanged($order));

        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof OrderStatusChanged && $job->notification->afterCommit === true);
    }

    public function test_a_notification_sent_inside_a_transaction_is_delivered_after_commit(): void
    {
        Notification::fake();
        $order = Order::factory()->create(['status' => 'processing']);

        DB::transaction(function () use ($order) {
            $order->user->notify(new OrderStatusChanged($order));
        });

        Notification::assertSentTo($order->user, OrderStatusChanged::class);
    }

    public function test_queue_worker_is_scheduled_unless_the_queue_is_sync(): void
    {
        // routes/console.php was loaded with the test queue (sync): load it again as production would see it
        config(['queue.default' => 'database']);
        require base_path('routes/console.php');

        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:work --stop-when-empty --max-time=50')
            ->expectsOutputToContain('queue:prune-failed --hours=168')
            ->expectsOutputToContain('heartbeat');
    }

    public function test_queue_worker_is_not_scheduled_for_the_sync_queue(): void
    {
        $this->assertSame('sync', config('queue.default'));
        $this->artisan('schedule:list')->doesntExpectOutputToContain('queue:work')->expectsOutputToContain('heartbeat');
    }

    public function test_jobs_tables_exist(): void
    {
        $this->assertTrue(\Schema::hasTable('jobs'));
        $this->assertTrue(\Schema::hasTable('failed_jobs'));
        $this->assertInstanceOf(User::class, User::factory()->create());
    }
}

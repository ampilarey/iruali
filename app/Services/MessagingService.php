<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Traits\SecureFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Messages between a customer, a shop and iruali support about one shop's part of an order.
 */
class MessagingService
{
    use SecureFileUpload;

    public const DISK = 'local';

    /** Days after the order is placed during which messages can still be sent. */
    public const WINDOW_DAYS = 90;

    /** Minutes between "new message" notifications to the same person about the same thread. */
    public const NOTIFY_EVERY_MINUTES = 10;

    /**
     * Messages are possible while the order is less than 90 days old, or while a return or dispute on it is open.
     */
    public function isOpenFor(Order $order): bool
    {
        if ($order->created_at && $order->created_at->copy()->addDays(self::WINDOW_DAYS)->isFuture()) {
            return true;
        }
        if (ReturnRequest::where('order_id', $order->id)->whereIn('status', ['requested', 'approved'])->exists()) {
            return true;
        }
        if (class_exists(\App\Models\Dispute::class) && \App\Models\Dispute::where('order_id', $order->id)->open()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * The thread for one shop's part, created on first use.
     */
    public function conversationFor(SellerOrder $part): Conversation
    {
        $order = $part->order;

        return Conversation::firstOrCreate(
            ['order_id' => $order->id, 'seller_id' => $part->seller_id],
            ['seller_order_id' => $part->id, 'customer_id' => $order->user_id, 'status' => 'open']
        );
    }

    public function existingConversationFor(SellerOrder $part): ?Conversation
    {
        return Conversation::where('order_id', $part->order_id)->where('seller_id', $part->seller_id)->first();
    }

    /**
     * Add a message, bump the other participants' unread counts and tell them (at most once
     * every ten minutes per thread and person).
     */
    public function send(Conversation $conversation, User $sender, string $role, string $body, ?UploadedFile $attachment = null): Message
    {
        $path = null;
        if ($attachment) {
            $path = $attachment->storeAs('message-attachments', $this->generateSecureFilename($attachment), self::DISK) ?: null;
        }

        $message = DB::transaction(function () use ($conversation, $sender, $role, $body, $path) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'sender_role' => $role,
                'body' => trim($body),
                'attachment_path' => $path,
            ]);

            $bumps = ['last_message_at' => now()];
            foreach (array_diff(Conversation::ROLES, [$role]) as $other) {
                $bumps[$other.'_unread_count'] = $conversation->unreadFor($other) + 1;
            }
            $conversation->forceFill($bumps)->save();

            return $message;
        });

        $this->notify($conversation->fresh(['customer', 'seller', 'order']), $message);

        return $message;
    }

    /**
     * The participant with this role has seen the thread.
     */
    public function markRead(Conversation $conversation, string $role): void
    {
        if (! in_array($role, Conversation::ROLES, true)) {
            return;
        }
        if ($conversation->unreadFor($role) > 0) {
            $conversation->forceFill([$role.'_unread_count' => 0])->save();
        }
        if ($role !== 'admin') {
            $conversation->messages()->whereNull('read_at')->where('sender_role', '!=', $role)->update(['read_at' => now()]);
        }
    }

    /**
     * Unread messages waiting for this user, as a customer and as a shop (admins: everything).
     *
     * @return array{customer: int, seller: int, admin: int}
     */
    public function unreadCounts(?User $user): array
    {
        if (! $user) {
            return ['customer' => 0, 'seller' => 0, 'admin' => 0];
        }

        return [
            'customer' => (int) Conversation::where('customer_id', $user->id)->sum('customer_unread_count'),
            'seller' => $user->is_seller ? (int) Conversation::where('seller_id', $user->id)->sum('seller_unread_count') : 0,
            'admin' => $user->isAdmin() ? (int) Conversation::sum('admin_unread_count') : 0,
        ];
    }

    public function attachmentResponse(Message $message)
    {
        abort_unless($message->attachment_path && Storage::disk(self::DISK)->exists($message->attachment_path), 404);

        return Storage::disk(self::DISK)->response($message->attachment_path);
    }

    protected function notify(Conversation $conversation, Message $message): void
    {
        $recipients = match ($message->sender_role) {
            'customer' => [$conversation->seller],
            'seller' => [$conversation->customer],
            default => [$conversation->customer, $conversation->seller],
        };

        foreach (array_filter($recipients) as $recipient) {
            // One alert per thread and person every ten minutes; the thread itself shows everything
            if (! Cache::add('new-message:'.$conversation->id.':'.$recipient->id, true, now()->addMinutes(self::NOTIFY_EVERY_MINUTES))) {
                continue;
            }
            try {
                $recipient->notify(new NewMessage($message));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}

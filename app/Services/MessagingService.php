<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ItemRequest;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\Audit;
use App\Support\CurrentShop;
use App\Support\TeamAlerts;
use App\Traits\SecureFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The messages centre: threads about an order's shop part, an item before buying, with iruali support
 * (customers and shops) and about an item request (Conversation::KINDS). iruali's team sees every thread
 * (Admin → Messages) and can write in any of them. Nothing is ever deleted. In item questions phone
 * numbers, email addresses and links are hidden from the other side (mask()), so sales stay on iruali;
 * the team still sees what was written. A message can be reported, and the team can stop an account from
 * writing (users.messaging_blocked_at).
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

    /** A customer's questions about an item, with the shop that sells it (one thread per customer and item). */
    public function productConversation(User $customer, Product $product): Conversation
    {
        return Conversation::firstOrCreate(
            ['kind' => Conversation::PRODUCT, 'customer_id' => $customer->id, 'product_id' => $product->id],
            ['seller_id' => $product->seller_id, 'subject' => Str::limit((string) ($product->getTranslation('name', 'en', false) ?: $product->name), 190), 'status' => 'open']
        );
    }

    /** The customer's open thread with iruali support, or a new one (saved with its first message). */
    public function supportConversation(User $customer): Conversation
    {
        return Conversation::where('kind', Conversation::SUPPORT)->where('customer_id', $customer->id)->where('status', 'open')->latest('id')->first()
            ?? new Conversation(['kind' => Conversation::SUPPORT, 'customer_id' => $customer->id, 'status' => 'open']);
    }

    /** The shop's open thread with iruali support, or a new one. */
    public function shopSupportConversation(User $shop): Conversation
    {
        return Conversation::where('kind', Conversation::SHOP_SUPPORT)->where('seller_id', $shop->id)->where('status', 'open')->latest('id')->first()
            ?? new Conversation(['kind' => Conversation::SHOP_SUPPORT, 'seller_id' => $shop->id, 'status' => 'open']);
    }

    /**
     * A visitor without an account writes to iruali, or about an item (always a new conversation): their
     * name and email and/or mobile are kept on it, with a secret token for their private link.
     */
    public function guestConversation(string $kind, string $name, ?string $email, ?string $phone, ?Product $product = null): Conversation
    {
        $conversation = new Conversation([
            'kind' => $kind,
            'guest_name' => Str::limit(trim($name), 95),
            'guest_email' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'guest_phone' => filled($phone) ? \App\Services\Sms\PhoneNumber::normalize((string) $phone) : null,
            'status' => 'open',
        ]);
        if ($product) {
            $conversation->fill(['product_id' => $product->id, 'seller_id' => $product->seller_id, 'subject' => Str::limit((string) ($product->getTranslation('name', 'en', false) ?: $product->name), 190)]);
        }
        $conversation->access_token = Str::random(48);

        return $conversation;
    }

    /** The thread between the customer and iruali about an item request. */
    public function itemRequestConversation(ItemRequest $request): Conversation
    {
        return Conversation::firstOrCreate(
            ['kind' => Conversation::ITEM_REQUEST, 'item_request_id' => $request->id],
            ['customer_id' => $request->user_id, 'subject' => Str::limit($request->title, 190), 'status' => 'open']
        );
    }

    /**
     * Phone numbers, email addresses and links (and @handles) replaced, for item questions.
     */
    public static function mask(string $text): string
    {
        $hidden = __('[contact details hidden]');
        $patterns = [
            '/[\w.+-]+@[\w-]+(\.[\w-]+)+/u',                                    // email addresses
            '#\b(?:https?://|www\.)\S+#iu',                                        // links
            '/\b[\w-]+\.(?:com|mv|net|org|io|me|co|shop|store|app|link|ly)\b\S*/iu', // bare domains
            '/\b(?:wa\.me|t\.me|viber|telegram|whatsapp|instagram|insta|facebook|fb|tiktok|snapchat)\b\S*/iu',
            '/(?<![\w@])@[A-Za-z0-9_.]{3,}/u',                                     // @handles
            '/(?:\+?\d[\s().-]*){7,}/u',                                          // phone numbers: 7+ digits
        ];

        return preg_replace($patterns, $hidden, $text) ?? $text;
    }

    /**
     * Add a message, bump the other participants' unread counts and tell them (at most once
     * every ten minutes per thread and person).
     */
    public function send(Conversation $conversation, ?User $sender, string $role, string $body, ?UploadedFile $attachment = null, bool $notify = true): Message
    {
        if ($role !== 'admin' && $sender?->messaging_blocked_at !== null) {
            throw new RuntimeException(__('Your account can no longer send messages. Please contact iruali.'));
        }
        $body = trim($body);
        $original = null;
        if ($role !== 'admin' && $conversation->hidesContactDetails() && ($masked = self::mask($body)) !== $body) {
            [$original, $body] = [$body, $masked];
        }
        if (! $conversation->exists) {
            $conversation->save();
        }

        $path = null;
        if ($attachment) {
            $path = $attachment->storeAs('message-attachments', $this->generateSecureFilename($attachment), self::DISK) ?: null;
        }

        $message = DB::transaction(function () use ($conversation, $sender, $role, $body, $original, $path) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender?->id, // null: a visitor without an account
                'sender_role' => $role,
                'body' => $body,
                'original_body' => $original,
                'attachment_path' => $path,
            ]);

            $bumps = ['last_message_at' => now()];
            foreach (array_diff(Conversation::ROLES, [$role]) as $other) {
                $bumps[$other.'_unread_count'] = $conversation->unreadFor($other) + 1;
            }
            $conversation->forceFill($bumps)->save();

            return $message;
        });

        // A dispute's own notifications already cover its opening and decision messages
        if ($notify) {
            $this->notify($conversation->fresh(['customer', 'seller', 'order']), $message);
        }

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
     * Unread messages waiting for this user, as a customer and as a shop (admins: everything). The
     * shop's count also shows for its staff who answer customers.
     *
     * @return array{customer: int, seller: int, admin: int}
     */
    public function unreadCounts(?User $user): array
    {
        if (! $user) {
            return ['customer' => 0, 'seller' => 0, 'admin' => 0];
        }

        $shopId = $this->shopIdFor($user);

        return [
            'customer' => (int) Conversation::where('customer_id', $user->id)->sum('customer_unread_count'),
            'seller' => $shopId ? (int) Conversation::where('seller_id', $shopId)->sum('seller_unread_count') : 0,
            'admin' => Conversation::isTeam($user) ? (int) Conversation::sum('admin_unread_count') : 0,
        ];
    }

    /**
     * The shop whose messages this user answers: their own, or the shop they work for when their
     * staff role may write to customers. 0 for nobody.
     */
    protected function shopIdFor(User $user): int
    {
        if ($user->is_seller) {
            return (int) $user->id;
        }
        $shopId = (int) CurrentShop::membershipOf($user)?->shop_id;

        return CurrentShop::worksFor($user, $shopId, 'seller.orders.messages.store') ? $shopId : 0;
    }

    public function attachmentResponse(Message $message)
    {
        abort_unless($message->attachment_path && Storage::disk(self::DISK)->exists($message->attachment_path), 404);

        return Storage::disk(self::DISK)->response($message->attachment_path);
    }

    /**
     * A participant reports a message to iruali's team (Admin → Messages → Reported).
     */
    public function report(Message $message, User $user, string $reason): void
    {
        $conversation = $message->conversation;
        $role = $conversation?->roleOf($user);
        if (! $conversation || ! in_array($role, ['customer', 'seller'], true) || $message->sender_role === $role) {
            throw new RuntimeException(__('You can report only messages written to you.'));
        }
        $message->forceFill(['reported_at' => now(), 'reported_by' => $user->id, 'report_reason' => Str::limit(trim($reason), 490)])->save();
        $conversation->forceFill(['admin_unread_count' => $conversation->admin_unread_count + 1])->save();
        TeamAlerts::send('message_reported', 'iruali: a message was reported in '.$conversation->title().': '.Str::limit(trim($reason), 100), route('admin.messages.show', $conversation));
    }

    /** iruali's team stops (or lets again) an account write messages; what it wrote stays. */
    public function block(User $user, User $admin, bool $blocked = true): void
    {
        $user->forceFill(['messaging_blocked_at' => $blocked ? now() : null])->save();
        Audit::record($blocked ? 'messaging.blocked' : 'messaging.unblocked', $user, ['by' => $admin->name]);
    }

    protected function notify(Conversation $conversation, Message $message): void
    {
        // A visitor without an account hears by email and/or SMS, on the details they gave
        $customer = $conversation->customer ?? $this->guestRecipient($conversation);
        $recipients = match ($message->sender_role) {
            'customer' => [$conversation->seller],
            'seller' => [$customer],
            default => [$customer, $conversation->seller],
        };

        // Written to iruali (support, item requests): the team hears about it
        if ($message->sender_role !== 'admin' && in_array($conversation->kind, [Conversation::SUPPORT, Conversation::SHOP_SUPPORT, Conversation::ITEM_REQUEST], true)) {
            TeamAlerts::send('customer_message', 'iruali: new message in '.$conversation->title().' from '.$conversation->customerName().': '.Str::limit($message->body, 100), route('admin.messages.show', $conversation));
            $recipients = [];
        }

        foreach (array_filter($recipients) as $recipient) {
            // One alert per thread and person every ten minutes; the thread itself shows everything
            $key = $recipient instanceof User ? $recipient->id : 'guest';
            if (! Cache::add('new-message:'.$conversation->id.':'.$key, true, now()->addMinutes(self::NOTIFY_EVERY_MINUTES))) {
                continue;
            }
            try {
                $recipient->notify(new NewMessage($message));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** The visitor's email and/or mobile as a notification route, or null. */
    protected function guestRecipient(Conversation $conversation): ?\Illuminate\Notifications\AnonymousNotifiable
    {
        if (! $conversation->isGuest()) {
            return null;
        }
        $routes = array_filter(['mail' => $conversation->guest_email, 'sms' => $conversation->guest_phone]);
        if ($routes === []) {
            return null;
        }
        $notifiable = new \Illuminate\Notifications\AnonymousNotifiable;
        foreach ($routes as $channel => $to) {
            $notifiable->route($channel, $to);
        }

        return $notifiable;
    }
}

<?php

namespace App\Models;

use App\Support\CurrentShop;
use App\Support\StaffAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A message thread (App\Services\MessagingService), one of:
 *  - order: one shop's part of an order, between the customer, the shop and iruali support;
 *  - product: a customer asking about an item before buying ("Ask about this item"), with the shop
 *    that sells it;
 *  - support: a customer and iruali ("Message iruali");
 *  - shop_support: a shop and iruali (Seller Centre → Messages → "Message iruali");
 *  - item_request: a customer and iruali about an item request.
 * iruali's team (admins, and staff who may open Admin → Messages) sees and can join every thread.
 * Conversations and their messages are never deleted (see booted() and the migrations).
 */
class Conversation extends Model
{
    public const ROLES = ['customer', 'seller', 'admin'];

    public const ORDER = 'order';

    public const PRODUCT = 'product';

    public const SUPPORT = 'support';

    public const SHOP_SUPPORT = 'shop_support';

    public const ITEM_REQUEST = 'item_request';

    public const KINDS = [self::ORDER, self::PRODUCT, self::SUPPORT, self::SHOP_SUPPORT, self::ITEM_REQUEST];

    protected $fillable = [
        'kind', 'order_id', 'seller_order_id', 'product_id', 'item_request_id', 'subject', 'customer_id', 'seller_id', 'last_message_at',
        'customer_unread_count', 'seller_unread_count', 'admin_unread_count', 'status',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'customer_unread_count' => 'integer',
        'seller_unread_count' => 'integer',
        'admin_unread_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Every conversation is kept for iruali to check later: nothing deletes one
        static::deleting(fn () => throw new LogicException('Conversations are never deleted.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ItemRequest, $this> */
    public function itemRequest(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** iruali's team: admins, and staff who may open Admin → Messages (support). */
    public static function isTeam(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || ($user->isStaff() && StaffAccess::can('admin.messages', $user)));
    }

    /**
     * How this user takes part: 'customer', 'seller', 'admin' (iruali's team) or null for an outsider.
     */
    public function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        if ($this->customer_id && $user->id === $this->customer_id) {
            return 'customer';
        }
        if ($this->seller_id && $user->id === $this->seller_id) {
            return 'seller';
        }
        // The shop's staff who can open its order pages (order threads) or its messages take part for the shop
        $route = $this->kind === self::ORDER ? 'seller.orders.show' : 'seller.messages';
        if ($this->seller_id && CurrentShop::worksFor($user, (int) $this->seller_id, $route)) {
            return 'seller';
        }

        return self::isTeam($user) ? 'admin' : null;
    }

    public function shopName(): string
    {
        return $this->seller ? ($this->seller->business_name ?: $this->seller->name) : 'iruali';
    }

    public function unreadFor(string $role): int
    {
        return (int) ($this->{$role.'_unread_count'} ?? 0);
    }

    /** "Order IR-123", "About: Blue kettle", "iruali support", "Request #4". */
    public function title(): string
    {
        return match ($this->kind) {
            self::PRODUCT => __('About: :item', ['item' => $this->subject ?: ($this->product_id ? $this->product->name : __('an item'))]),
            self::SUPPORT, self::SHOP_SUPPORT => __('iruali support'),
            self::ITEM_REQUEST => $this->itemRequest ? $this->itemRequest->label().' · '.$this->itemRequest->title : __('Item request'),
            default => __('Order :number', ['number' => $this->order_id ? $this->order->order_number : '—']),
        };
    }

    /** Contact details are hidden in item questions, so sales stay on iruali (MessagingService::mask()). */
    public function hidesContactDetails(): bool
    {
        return $this->kind === self::PRODUCT;
    }

    /** The page where this person reads and answers the thread. */
    public function urlFor(string $role): string
    {
        if ($this->kind === self::ORDER && $this->order) {
            return match ($role) {
                'seller' => route('seller.orders.show', $this->order).'#conversation-'.$this->id,
                'admin' => route('admin.messages.show', $this),
                default => route('orders.show', $this->order).'#conversation-'.$this->id,
            };
        }

        return match ($role) {
            'seller' => route('seller.messages.show', $this),
            'admin' => route('admin.messages.show', $this),
            default => route('messages.show', $this),
        };
    }
}

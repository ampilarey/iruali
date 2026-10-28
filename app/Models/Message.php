<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One message in a conversation. Never deleted. When contact details were hidden from the other side
 * (item questions), body is what they see and original_body what was written, for admins.
 */
class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'sender_role', 'body', 'original_body', 'attachment_path', 'read_at'];

    protected $casts = ['read_at' => 'datetime', 'reported_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Messages are never deleted.'));
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * How the sender is shown to whoever reads it ($viewerRole). Customers see one store: the shop's and
     * the team's replies both show as "iruali". Shops see a customer's shortened name in item questions;
     * the team sees everyone by name.
     */
    public function senderName(?string $viewerRole = null): string
    {
        $conversation = $this->conversation;

        return match ($this->sender_role) {
            'admin' => __('iruali support'),
            'seller' => $viewerRole === 'customer' ? 'iruali' : ($conversation?->shopName() ?? ($this->sender->name ?? __('Shop'))),
            default => $viewerRole === 'seller' && $conversation?->hidesContactDetails()
                ? \App\Services\PointsService::maskName($this->sender->name ?? __('Customer'))
                : ($this->sender->name ?? __('Customer')),
        };
    }

    public function wasMasked(): bool
    {
        return $this->original_body !== null && $this->original_body !== $this->body;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'sender_role', 'body', 'attachment_path', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * How the sender is shown: the customer's name, the shop's name, or "iruali support".
     */
    public function senderName(): string
    {
        return match ($this->sender_role) {
            'admin' => __('iruali support'),
            'seller' => $this->conversation?->shopName() ?? ($this->sender?->name ?? __('Shop')),
            default => $this->sender?->name ?? __('Customer'),
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One text message we tried to send: which kind (App\Support\SmsTypes), where, what, and whether the
 * gateway accepted it. Status: sent, logged (log driver), failed, invalid (number), held (quiet
 * hours, goes at send_after) or blocked (all SMS off, or the monthly limit).
 */
class SmsMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'to', 'message', 'status', 'provider_response', 'cost', 'send_after', 'created_at'];

    protected $casts = ['cost' => 'decimal:4', 'send_after' => 'datetime', 'created_at' => 'datetime'];

    public function getStatusBadgeAttribute(): string
    {
        return [
            'sent' => 'bg-green-100 text-green-800',
            'logged' => 'bg-gray-100 text-gray-700',
            'failed' => 'bg-red-100 text-red-800',
            'invalid' => 'bg-yellow-100 text-yellow-800',
            'held' => 'bg-blue-100 text-blue-800',
            'blocked' => 'bg-orange-100 text-orange-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-700';
    }
}

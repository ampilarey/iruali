<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One text message we tried to send: where, what, and whether the gateway accepted it.
 */
class SmsMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['to', 'message', 'status', 'provider_response', 'cost', 'created_at'];

    protected $casts = ['cost' => 'decimal:4', 'created_at' => 'datetime'];

    public function getStatusBadgeAttribute(): string
    {
        return [
            'sent' => 'bg-green-100 text-green-800',
            'logged' => 'bg-gray-100 text-gray-700',
            'failed' => 'bg-red-100 text-red-800',
            'invalid' => 'bg-yellow-100 text-yellow-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-700';
    }
}

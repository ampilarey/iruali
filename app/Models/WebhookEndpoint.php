<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An address another system gave iruali to be told about orders (Admin → Webhooks).
 */
class WebhookEndpoint extends Model
{
    protected $fillable = ['url', 'secret', 'events', 'is_active'];

    protected $casts = ['events' => 'array', 'is_active' => 'boolean', 'last_sent_at' => 'datetime'];

    protected $hidden = ['secret'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SMS sent to a segment of customers (Admin → SMS campaigns), now or at a set time.
 */
class SmsCampaign extends Model
{
    protected $fillable = ['name', 'message', 'segment', 'segment_value', 'status', 'scheduled_at', 'created_by'];

    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }
}

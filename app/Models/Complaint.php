<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A complaint sent with the public form (Contact → Make a complaint), handled in Admin → Complaints.
 */
class Complaint extends Model
{
    public const DISK = 'local';

    /** category => label (English source text, translated on the form) */
    public const CATEGORIES = [
        'delivery' => 'Delivery (late, lost or damaged)',
        'product' => 'Product (wrong, faulty, not as described)',
        'shop' => 'A shop',
        'payment' => 'Payment or refund',
        'website' => 'The website or app',
        'other' => 'Something else',
    ];

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    /** Still needs someone. */
    public const OPEN = ['open', 'in_progress'];

    protected $fillable = ['user_id', 'order_id', 'name', 'email', 'phone', 'category', 'details', 'photos'];

    protected $casts = [
        'photos' => 'array',
        'notes' => 'array',
        'resolved_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Complaint $complaint) {
            do {
                $reference = 'C-'.strtoupper(Str::random(6));
            } while (static::where('reference', $reference)->exists());
            $complaint->reference = $reference;
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Complaint>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

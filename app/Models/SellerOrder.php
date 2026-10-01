<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One shop's part of a customer order: its fulfilment status and tracking, and what the shop earns.
 */
class SellerOrder extends Model
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    /** Order of progress; cancelled is outside it. */
    public const RANK = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];

    protected $fillable = [
        'order_id', 'seller_id', 'status', 'tracking_note', 'shipped_at', 'delivered_at',
        'subtotal', 'commission_rate', 'commission_amount', 'seller_earnings', 'payout_id',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_earnings' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'payout_id');
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * The order's items that belong to this shop.
     */
    public function items()
    {
        return $this->order->items()->whereHas('product', fn ($q) => $q->withTrashed()->where('seller_id', $this->seller_id));
    }

    public function shopName(): string
    {
        return $this->seller ? ($this->seller->business_name ?: $this->seller->name) : 'iruali';
    }

    /**
     * Where the shop's money for this part stands:
     * cancelled → none; paid_out → in a payout; processing → in a payout batch not yet paid;
     * available → delivered and the customer has paid; pending → not yet.
     */
    public function earningsState(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->payout_id) {
            return $this->payout && ! $this->payout->isPaid() ? 'processing' : 'paid_out';
        }

        return $this->status === 'delivered' && $this->order?->payment_status === 'paid' ? 'available' : 'pending';
    }

    /**
     * Parts whose earnings can go into a payout now.
     */
    public function scopePayable($query)
    {
        return $query->whereNull('payout_id')
            ->where('status', 'delivered')
            ->where('seller_earnings', '>', 0)
            ->whereHas('order', fn ($o) => $o->where('payment_status', 'paid'));
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}

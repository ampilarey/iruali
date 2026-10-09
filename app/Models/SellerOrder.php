<?php

namespace App\Models;

use App\Enums\SellerOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One shop's part of a customer order: its fulfilment status and tracking, and what the shop earns.
 */
class SellerOrder extends Model
{
    /** Delivery details a shop (or admin) can record on a part. */
    public const TRACKING_FIELDS = ['courier', 'tracking_number', 'tracking_url', 'vessel_or_flight', 'expected_delivery_date'];

    protected $fillable = [
        'order_id', 'seller_id', 'status', 'tracking_note', 'shipped_at', 'out_for_delivery_at', 'delivered_at',
        'courier', 'tracking_number', 'tracking_url', 'vessel_or_flight', 'expected_delivery_date',
        'subtotal', 'commission_rate', 'commission_amount', 'seller_earnings', 'payout_id',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_earnings' => 'decimal:2',
        // GST as it was when the order was placed (App\Services\GstService)
        'gst_registered' => 'boolean',
        'gst_rate' => 'decimal:2',
        'gst_taxable' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'commission_gst' => 'decimal:2',
        'gst_captured_at' => 'datetime',
        'invoice_sequence' => 'integer',
        'invoiced_at' => 'datetime',
        'gst_reversed_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<SellerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'payout_id');
    }

    /** @return HasMany<ReturnRequest, $this> */
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
        return SellerOrderStatus::badgeFor($this->status);
    }

    public function statusEnum(): ?SellerOrderStatus
    {
        return SellerOrderStatus::tryFrom((string) $this->status);
    }

    public function hasTrackingDetails(): bool
    {
        return filled($this->courier) || filled($this->tracking_number) || filled($this->tracking_url)
            || filled($this->vessel_or_flight) || $this->expected_delivery_date !== null;
    }

    /**
     * The delivery details in one line, for texts and emails: courier and number, boat or flight,
     * expected date, link; or the free-text note from before these fields existed.
     */
    public function trackingSummary(): string
    {
        $bits = [];
        if ($this->courier || $this->tracking_number) {
            $bits[] = trim($this->courier.' '.$this->tracking_number);
        }
        if ($this->vessel_or_flight) {
            $bits[] = $this->vessel_or_flight;
        }
        if ($this->expected_delivery_date) {
            $bits[] = __('expected :date', ['date' => $this->expected_delivery_date->translatedFormat('j M')]);
        }
        if ($this->tracking_url) {
            $bits[] = $this->tracking_url;
        }
        if (! $bits && $this->tracking_note) {
            $bits[] = $this->tracking_note;
        }

        return implode(' · ', $bits);
    }

    /**
     * The message thread between the customer, this shop and iruali support about this part.
     *
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'seller_order_id');
    }

    /** @return HasMany<Dispute, $this> */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * The shop's goods total after any shop-level discount: what its GST is worked out on (prices
     * include GST). The one place for it; take the shop discount (shop_discount) off here.
     */
    public function taxableGoodsTotal(): float
    {
        return round((float) $this->subtotal, 2);
    }
}

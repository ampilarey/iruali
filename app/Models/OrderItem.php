<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Keep each shop's part of the order (status and earnings) in step with the items.
     */
    protected static function booted(): void
    {
        static::created(function (OrderItem $item) {
            if ($item->order) {
                app(\App\Services\FulfilmentService::class)->refreshPart($item->order, $item->product?->seller_id ?: null);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

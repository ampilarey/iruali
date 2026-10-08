<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Route;

/**
 * Who did what, to which record, with what changes. Written through App\Support\Audit::record().
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip', 'user_agent', 'created_at'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** A short label for the subject, e.g. "Order #ORD-000123". */
    public function subjectLabel(): string
    {
        if (! $this->subject_type) {
            return '—';
        }

        return class_basename($this->subject_type).' #'.$this->subject_id;
    }

    /** The admin page for the subject, when there is one. */
    public function subjectUrl(): ?string
    {
        if (! $this->subject_id) {
            return null;
        }

        $route = match ($this->subject_type) {
            Order::class => ['admin.orders.show', 'order'],
            SellerPayout::class => ['admin.payouts.show', 'payout'],
            ReturnRequest::class => ['admin.returns.show', 'return'],
            Voucher::class => ['admin.vouchers.edit', 'voucher'],
            User::class => ['admin.users', null],
            Product::class => ['admin.products', null],
            Brand::class => ['admin.brands.edit', 'brand'],
            default => null,
        };

        if (! $route || ! Route::has($route[0])) {
            return null;
        }

        return $route[1] ? route($route[0], [$route[1] => $this->subject_id]) : route($route[0]);
    }
}

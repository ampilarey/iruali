<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'code', 'type', 'amount', 'min_order', 'max_uses', 'used_count', 'valid_from', 'valid_until', 'is_active'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'min_order' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];
} 
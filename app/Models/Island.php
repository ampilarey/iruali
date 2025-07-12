<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Island extends Model
{
    protected $fillable = [
        'name', 'atoll', 'is_active'
    ];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_island')
            ->withPivot('stock_quantity', 'reorder_point', 'is_active')
            ->withTimestamps();
    }
} 
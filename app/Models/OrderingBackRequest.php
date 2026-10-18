<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone who asked to be told when ordering is back (OrderRules pause).
 */
class OrderingBackRequest extends Model
{
    protected $fillable = ['email', 'locale', 'notified_at'];

    protected $casts = ['notified_at' => 'datetime'];
}

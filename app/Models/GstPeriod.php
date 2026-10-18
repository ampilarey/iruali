<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A month whose GST return was filed: its figures as they were then, so later refunds or changes
 * show up as differences instead of quietly changing a filed return.
 */
class GstPeriod extends Model
{
    protected $fillable = ['month', 'totals', 'filing_reference', 'locked_by', 'locked_at'];

    protected $casts = ['totals' => 'array', 'locked_at' => 'datetime'];
}

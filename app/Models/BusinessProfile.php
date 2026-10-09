<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's business details for invoices: offered at checkout ("Buying for a business?"),
 * where the buyer can change them for that order.
 */
class BusinessProfile extends Model
{
    protected $fillable = ['user_id', 'company_name', 'tin', 'business_address'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(?int $userId): ?self
    {
        return $userId ? static::where('user_id', $userId)->first() : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop agreeing to a version of the Seller Terms (App\Support\SellerTerms::recordAgreement()).
 */
class SellerTermsAgreement extends Model
{
    protected $fillable = ['user_id', 'agreed_by', 'version', 'ip', 'user_agent'];

    /** @return BelongsTo<User, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function agreedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agreed_by');
    }
}

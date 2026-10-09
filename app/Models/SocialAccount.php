<?php

namespace App\Models;

use App\Services\SocialLoginService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google, Facebook or Apple account a customer signs in with (see SocialLoginService). The
 * subject is the provider's own id for the person, so a changed email still finds the account.
 */
class SocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'subject', 'email', 'last_used_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Google", "Facebook" or "Apple". */
    public function providerLabel(): string
    {
        return SocialLoginService::LABELS[$this->provider] ?? ucfirst($this->provider);
    }
}

<?php

namespace App\Models;

use App\Support\ShopStaffAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on a shop's staff: they sign in with their own account and work in the Seller Centre
 * for the shop (shop_id, the owner's account) within their role (config/shop_staff.php). One shop
 * per staff account. Removing the row takes their access away on their next request.
 */
class ShopStaff extends Model
{
    protected $table = 'shop_staff';

    /** shop_id, user_id and invited_by are set by ShopStaffService, never from input. */
    protected $fillable = ['role'];

    /** @return BelongsTo<User, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shop_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function roleLabel(): string
    {
        return ShopStaffAccess::roleLabel((string) $this->role);
    }
}

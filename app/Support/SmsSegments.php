<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who an SMS campaign goes to. Every segment only has customers with a verified phone who chose
 * SMS for offers (My Account → Notifications) and did not opt out of marketing; never staff or shops.
 */
class SmsSegments
{
    /** key => [label, what the value is (null: none)] */
    public const SEGMENTS = [
        'all' => ['Everyone who allows offers by SMS', null],
        'category' => ['Bought from a category', 'category'],
        'brand' => ['Follows a brand', 'brand'],
        'inactive' => ['Bought before, nothing in the last 90 days', null],
        'island' => ['Lives on an island (saved address or shopping island)', 'island'],
    ];

    /**
     * The customers in a segment who may get marketing SMS.
     *
     * @return Collection<int, User>
     */
    public static function recipients(string $segment, ?string $value): Collection
    {
        $query = User::query()->whereNotNull('phone')->whereNotNull('phone_verified_at')->whereNull('marketing_opt_out_at')
            ->where('is_active', true)->whereNull('erased_at')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', array_merge((array) config('staff.roles'), ['seller'])));
        self::narrow($query, $segment, $value);

        return $query->get(['id', 'name', 'email', 'phone', 'phone_verified_at', 'notification_preferences'])
            ->filter(fn (User $user) => in_array($user->notificationPreference('marketing'), ['sms', 'both'], true))->values();
    }

    /**
     * @param  Builder<User>  $query
     */
    protected static function narrow(Builder $query, string $segment, ?string $value): void
    {
        $id = (int) $value;
        match ($segment) {
            'category' => $query->whereIn('id', \App\Models\Order::query()->select('user_id')->where('payment_status', 'paid')
                ->whereHas('items.product', fn ($q) => $q->where('category_id', $id))),
            'brand' => $query->whereIn('id', \Illuminate\Support\Facades\DB::table('brand_follows')->where('brand_id', $id)->select('user_id')),
            'inactive' => $query->whereHas('orders', fn ($q) => $q->where('payment_status', 'paid'))
                ->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(90))),
            'island' => $query->where(fn ($q) => $q->where('shopping_island_id', $id)
                ->orWhereIn('id', \Illuminate\Support\Facades\DB::table('addresses')->where('island_id', $id)->select('user_id'))),
            default => null,
        };
    }

    /**
     * Characters per message part: 160 in plain Latin letters, 70 with Thaana or other characters.
     */
    public static function parts(string $text): int
    {
        $unicode = (bool) preg_match('/[^\x00-\x7F]/', $text);
        $length = mb_strlen($text);
        $single = $unicode ? 70 : 160;
        $multi = $unicode ? 67 : 153;

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}

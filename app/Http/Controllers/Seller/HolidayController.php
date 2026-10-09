<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\ShopHoliday;
use Illuminate\Http\Request;

/**
 * Seller Centre → Settings → Holiday mode. While it is on, the shop's products stay listed (search
 * engines keep them) but nobody can add them to a cart or order them; orders already placed carry on
 * as normal. It ends by itself on the back-on date, or when switched off here.
 */
class HolidayController extends Controller
{
    public function show(Request $request)
    {
        return view('seller.settings.holiday', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'on_holiday' => 'required|boolean',
            'holiday_until' => ['nullable', 'exclude_unless:on_holiday,1', 'date', 'after:today', 'before_or_equal:'.today()->addYear()->toDateString()],
            'holiday_message' => 'nullable|string|max:500',
        ], [
            'holiday_until.after' => __('The back-on date must be after today.'),
            'holiday_until.before_or_equal' => __('Choose a back-on date within the next year.'),
        ]);

        $on = (bool) $data['on_holiday'];
        $wasOn = $user->isOnHoliday();
        $message = trim((string) ($data['holiday_message'] ?? ''));

        $user->forceFill([
            'holiday_mode' => $on,
            'holiday_until' => $on ? ($data['holiday_until'] ?? null) : null,
            'holiday_message' => $message !== '' ? $message : null,
            'holiday_started_at' => $on ? ($wasOn ? $user->holiday_started_at : now()) : null,
        ])->save();

        if ($user->wasChanged(['holiday_mode', 'holiday_until'])) {
            Audit::record('seller.holiday', $user, ['on' => $on, 'until' => $user->holiday_until?->toDateString()]);
        }

        if (! $on) {
            return redirect()->route('seller.settings.holiday')->with('success', __('Holiday mode is off. Customers can order from your shop again.'));
        }

        return redirect()->route('seller.settings.holiday')->with('success', $user->holiday_until
            ? __('Holiday mode is on. Customers can see your products but cannot order until :date.', ['date' => ShopHoliday::date($user)])
            : __('Holiday mode is on. Customers can see your products but cannot order until you switch it off.'));
    }
}

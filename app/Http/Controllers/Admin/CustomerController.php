<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerNote;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Support\Audit;
use App\Support\LoyaltyTiers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Users → a customer: everything about one person on one page (orders, returns, disputes,
 * messages, quotes, points, wallet), tags and private notes, and (admins) a phone change, merging a
 * duplicate account and erasing personal data.
 */
class CustomerController extends Controller
{
    public function show(User $user)
    {
        $orders = Order::where('user_id', $user->id)->latest()->get();
        $paid = $orders->where('payment_status', 'paid')->where('status', '!=', 'cancelled');

        return view('admin.customers.show', [
            'customer' => $user,
            'orders' => $orders->take(25),
            'stats' => [
                'orders' => $orders->count(),
                'spent' => (float) $paid->sum('total_amount'),
                'average' => $paid->count() ? (float) $paid->avg('total_amount') : 0,
                'first' => $orders->last()?->created_at,
                'last' => $orders->first()?->created_at,
                'tier' => LoyaltyTiers::enabled() ? LoyaltyTiers::all()[LoyaltyTiers::current($user)]['name'] : null,
            ],
            'returns' => \App\Models\ReturnRequest::where('user_id', $user->id)->latest()->take(10)->get(),
            'disputes' => \App\Models\Dispute::where('customer_id', $user->id)->latest()->take(10)->get(),
            'quotes' => \App\Models\QuoteRequest::where('customer_id', $user->id)->latest()->take(10)->get(),
            'conversations' => \App\Models\Conversation::where('customer_id', $user->id)->latest('last_message_at')->take(10)->get(),
            'points' => \App\Models\PointsTransaction::where('user_id', $user->id)->latest('id')->take(10)->get(),
            'notes' => CustomerNote::with('author')->where('user_id', $user->id)->latest()->get(),
            'accounts' => app(CustomerAccountService::class),
        ]);
    }

    public function tags(Request $request, User $user)
    {
        $data = $request->validate(['tags' => 'nullable|string|max:300']);
        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => mb_substr(trim($t), 0, 30))->filter()->unique()->take(12)->values()->all();
        $user->forceFill(['staff_tags' => $tags ?: null])->save();

        return back()->with('success', 'Tags saved.');
    }

    public function note(Request $request, User $user)
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);
        CustomerNote::create(['user_id' => $user->id, 'author_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('success', 'Note added.');
    }

    public function phone(Request $request, User $user)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,15}$/', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);
        $phone = filled($data['phone'] ?? null) ? preg_replace('/\s+/', '', $data['phone']) : null;
        Audit::record('customer.phone_changed', $user, ['from' => $user->phone, 'to' => $phone]);
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => null])->save();

        return back()->with('success', 'Phone changed. The customer needs to verify the new number before codes go to it.');
    }

    public function merge(Request $request, User $user, CustomerAccountService $accounts)
    {
        $data = $request->validate(['into' => 'required|email|exists:users,email', 'confirm' => 'accepted'], ['into.exists' => 'No account has that email.']);
        $keep = User::where('email', $data['into'])->firstOrFail();

        if ($problem = $accounts->merge($user, $keep, $request->user())) {
            return back()->with('error', $problem);
        }

        return redirect()->route('admin.customers.show', $keep)->with('success', 'Merged: everything from the duplicate account is now on this one, and the duplicate is closed.');
    }

    public function erase(Request $request, User $user, CustomerAccountService $accounts)
    {
        $data = $request->validate(['reason' => 'required|string|max:200', 'confirm' => 'accepted']);
        if ($problem = $accounts->erase($user, $request->user(), $data['reason'])) {
            return back()->with('error', $problem);
        }

        return back()->with('success', 'Personal data erased. Orders stay for the records, without the customer\'s details.');
    }
}

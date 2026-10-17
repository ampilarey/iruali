<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use App\Notifications\ComplaintReceived;
use App\Support\TeamAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The public complaint form (footer → Make a complaint): anyone can send one, with up to three
 * photos. The sender gets a reference by email; the team gets an alert (Admin → Complaints).
 */
class ComplaintController extends Controller
{
    public function create(Request $request)
    {
        return view('feedback.complaint', ['user' => $request->user(), 'orderNumber' => (string) $request->query('order', '')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'order_number' => 'nullable|string|max:40',
            'category' => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'details' => 'required|string|min:10|max:3000',
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $complaint = Complaint::create([
            'user_id' => $request->user()?->id,
            'order_id' => $this->order($data['order_number'] ?? null, $data['email'], $request)?->id,
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'category' => $data['category'], 'details' => $data['details'],
            'photos' => collect($request->file('photos', []))->map(fn ($photo) => $photo->storeAs('complaint-photos', Str::random(24).'.'.$photo->extension(), Complaint::DISK))->values()->all() ?: null,
        ]);

        Notification::route('mail', $complaint->email)->notify(new ComplaintReceived($complaint->reference, app()->getLocale()));
        TeamAlerts::send('complaint', 'iruali: new complaint '.$complaint->reference.' ('.$complaint->categoryLabel().') from '.$complaint->name.'.', route('admin.complaints.show', $complaint));

        return redirect()->route('complaints.create')->with('complaint_reference', $complaint->reference);
    }

    /**
     * The order the complaint is about, only when it is the sender's own (signed in, or the email on it).
     */
    protected function order(?string $number, string $email, Request $request): ?Order
    {
        $order = filled($number) ? Order::with('user')->where('order_number', strtoupper(trim($number)))->first() : null;
        if (! $order) {
            return null;
        }
        $mine = ($request->user() && (int) $order->user_id === (int) $request->user()->id)
            || strcasecmp((string) ($order->user->email ?? $order->guest_email), $email) === 0;

        return $mine ? $order : null;
    }
}

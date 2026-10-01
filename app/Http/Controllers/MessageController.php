<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\MessagingService;
use Illuminate\Http\Request;

/**
 * Posting to an order thread as the customer, the shop or iruali support, and reading attachments.
 */
class MessageController extends Controller
{
    public function __construct(protected MessagingService $messaging) {}

    public function storeAsCustomer(Request $request, Order $order, SellerOrder $part)
    {
        abort_unless($order->user_id === $request->user()->id && $part->order_id === $order->id, 403);

        return $this->store($request, $part, 'customer', route('orders.show', $order).'#conversation');
    }

    public function storeAsSeller(Request $request, Order $order, SellerOrder $part)
    {
        abort_unless($part->order_id === $order->id && $part->seller_id === $request->user()->id, 403);

        return $this->store($request, $part, 'seller', route('seller.orders.show', $order).'#conversation');
    }

    public function storeAsAdmin(Request $request, Order $order, SellerOrder $part)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($part->order_id === $order->id, 404);

        return $this->store($request, $part, 'admin', route('admin.orders.show', $order).'#conversation');
    }

    public function attachment(Request $request, Message $message)
    {
        $this->authorize('view', $message->conversation);

        return $this->messaging->attachmentResponse($message);
    }

    protected function store(Request $request, SellerOrder $part, string $role, string $back)
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], ['attachment.max' => __('The photo must be 5 MB or smaller.')]);

        $conversation = $this->messaging->conversationFor($part);
        $this->authorize('reply', $conversation);

        if ($role !== 'admin' && ! $this->messaging->isOpenFor($part->order)) {
            return redirect($back)->withErrors(['body' => __('Messages about this order are closed: it is more than 90 days old and nothing is open on it.')], 'conversation-'.$conversation->id);
        }

        $this->messaging->send($conversation, $request->user(), $role, $data['body'], $request->file('attachment'));

        return redirect($back.'-'.$conversation->id)->with('success', __('Message sent.'));
    }
}

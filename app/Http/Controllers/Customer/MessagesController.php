<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ItemRequest;
use App\Models\Message;
use App\Models\Product;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The customer's messages: "My messages" (every thread they are in), "Ask about this item" on a product
 * page, "Message iruali", the thread on an item request, and reporting a message. iruali's team sees all
 * of it (Admin → Messages); nothing is deleted.
 */
class MessagesController extends Controller
{
    public function __construct(protected MessagingService $messaging) {}

    public function index(Request $request): View
    {
        return view('messages.index', [
            'conversations' => Conversation::where('customer_id', $request->user()->id)
                ->with(['order', 'product', 'itemRequest'])
                ->orderByDesc('customer_unread_count')->orderByDesc('last_message_at')->paginate(20),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View|RedirectResponse
    {
        abort_unless($conversation->roleOf($request->user()) === 'customer', 404);
        if ($conversation->kind === Conversation::ORDER) {
            return redirect($conversation->urlFor('customer'));
        }
        $this->messaging->markRead($conversation, 'customer');

        return view('messages.show', ['conversation' => $conversation->load(['messages.sender', 'product.mainImage', 'itemRequest']), 'postUrl' => route('messages.reply', $conversation)]);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->roleOf($request->user()) === 'customer' && $conversation->kind !== Conversation::ORDER, 404);

        return $this->post($request, $conversation, route('messages.show', $conversation));
    }

    /** "Message iruali": the customer's open thread with support (started by the first message). */
    public function support(Request $request): View
    {
        $conversation = $this->messaging->supportConversation($request->user());
        if ($conversation->exists) {
            $this->messaging->markRead($conversation, 'customer');
            $conversation->load('messages.sender');
        }

        return view('messages.show', ['conversation' => $conversation, 'postUrl' => route('messages.support.store')]);
    }

    public function storeSupport(Request $request): RedirectResponse
    {
        $conversation = $this->messaging->supportConversation($request->user());

        return $this->post($request, $conversation, null);
    }

    /** "Ask about this item": a private question to the shop selling it (shown to the customer as iruali). */
    public function ask(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->seller_id, 404);
        if ((int) $product->seller_id === (int) $request->user()->id) {
            return back()->with('error', __('This is your own product.'));
        }

        return $this->post($request, $this->messaging->productConversation($request->user(), $product), null);
    }

    public function itemRequest(Request $request, ItemRequest $itemRequest): RedirectResponse
    {
        abort_unless((int) $itemRequest->user_id === (int) $request->user()->id, 404);

        return $this->post($request, $this->messaging->itemRequestConversation($itemRequest), route('item-requests.show', $itemRequest).'#messages');
    }

    public function report(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        abort_unless($message->conversation?->roleOf($request->user()) !== null, 404);

        try {
            $this->messaging->report($message, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Thank you. iruali will look at this message.'));
    }

    protected function post(Request $request, Conversation $conversation, ?string $back): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], ['attachment.max' => __('The photo must be 5 MB or smaller.')]);
        if ($conversation->exists && ! $conversation->isOpen()) {
            return back()->with('error', __('This conversation is closed. Message iruali to start a new one.'));
        }

        try {
            $message = $this->messaging->send($conversation, $request->user(), 'customer', $data['body'], $request->file('attachment'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($back ?? route('messages.show', $conversation))->with('success', $message->original_body !== null
            ? __('Message sent. Phone numbers, emails and links were hidden: please keep the conversation on iruali.')
            : __('Message sent.'));
    }
}

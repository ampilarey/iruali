<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\MessagingService;
use App\Support\CurrentShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Seller Centre → Messages: every thread of the shop (customers' questions about its items, its order
 * threads, and its own thread with iruali support), unread first. Customers' names are shortened in item
 * questions, and their contact details hidden. iruali's team sees all of it; nothing is deleted.
 */
class MessagesController extends Controller
{
    public function __construct(protected MessagingService $messaging) {}

    public function index(Request $request): View
    {
        $kind = in_array($request->query('kind'), Conversation::KINDS, true) ? $request->query('kind') : null;

        return view('seller.messages.index', [
            'kind' => $kind,
            'conversations' => Conversation::where('seller_id', CurrentShop::id())
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->with(['order', 'product', 'customer'])
                ->orderByDesc('seller_unread_count')->orderByDesc('last_message_at')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Conversation $conversation): View|RedirectResponse
    {
        abort_unless((int) $conversation->seller_id === (int) CurrentShop::id(), 404);
        if ($conversation->kind === Conversation::ORDER) {
            return redirect($conversation->urlFor('seller'));
        }
        $this->messaging->markRead($conversation, 'seller');

        return view('seller.messages.show', ['conversation' => $conversation->load(['messages.sender', 'product.mainImage', 'customer']), 'postUrl' => route('seller.messages.reply', $conversation)]);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless((int) $conversation->seller_id === (int) CurrentShop::id() && $conversation->kind !== Conversation::ORDER, 404);

        return $this->post($request, $conversation);
    }

    /** "Message iruali" for the shop. */
    public function support(): View
    {
        $conversation = $this->messaging->shopSupportConversation(CurrentShop::get());
        if ($conversation->exists) {
            $this->messaging->markRead($conversation, 'seller');
            $conversation->load('messages.sender');
        }

        return view('seller.messages.show', ['conversation' => $conversation, 'postUrl' => route('seller.messages.support.store')]);
    }

    public function storeSupport(Request $request): RedirectResponse
    {
        return $this->post($request, $this->messaging->shopSupportConversation(CurrentShop::get()));
    }

    protected function post(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], ['attachment.max' => __('The photo must be 5 MB or smaller.')]);
        if ($conversation->exists && ! $conversation->isOpen()) {
            return back()->with('error', __('This conversation is closed.'));
        }

        try {
            $message = $this->messaging->send($conversation, $request->user(), 'seller', $data['body'], $request->file('attachment'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('seller.messages.show', $conversation)->with('success', $message->original_body !== null
            ? __('Message sent. Phone numbers, emails and links were hidden: customers are served on iruali only (Seller Terms).')
            : __('Message sent.'));
    }
}

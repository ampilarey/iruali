<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Admin → Messages: every conversation on iruali (orders, item questions, support, shops, item requests),
 * unread first, with reported messages on their own tab. The team reads any thread in full (what was
 * written, also where contact details were hidden from the other side), writes in it as iruali support,
 * closes or reopens it, and can stop an account from writing. Nothing is ever deleted.
 */
class MessageInboxController extends Controller
{
    public function __construct(protected MessagingService $messaging) {}

    public function index(Request $request)
    {
        $filter = in_array($request->query('show'), ['unread', 'open', 'reported', 'all'], true) ? $request->query('show') : 'unread';
        $kind = in_array($request->query('kind'), Conversation::KINDS, true) ? $request->query('kind') : null;

        $conversations = Conversation::with(['order', 'customer', 'seller', 'product', 'itemRequest'])
            ->when($filter === 'unread', fn ($q) => $q->where('admin_unread_count', '>', 0))
            ->when($filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($filter === 'reported', fn ($q) => $q->whereHas('messages', fn ($m) => $m->whereNotNull('reported_at')))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('admin_unread_count')
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.messages.index', [
            'conversations' => $conversations, 'filter' => $filter, 'kind' => $kind,
            'unread' => Conversation::where('admin_unread_count', '>', 0)->count(),
            'reported' => Message::whereNotNull('reported_at')->distinct()->count('conversation_id'),
        ]);
    }

    public function show(Conversation $conversation)
    {
        $this->messaging->markRead($conversation, 'admin');

        return view('admin.messages.show', ['conversation' => $conversation->load(['messages.sender', 'messages.reportedBy', 'customer', 'seller', 'order', 'product', 'itemRequest'])]);
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);
        $this->messaging->send($conversation, $request->user(), 'admin', $data['body'], $request->file('attachment'));

        return redirect()->route('admin.messages.show', $conversation)->with('success', 'Message sent as iruali support.');
    }

    /**
     * Close a thread (no more replies from the customer or the shop) or open it again.
     */
    public function status(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['status' => 'required|in:open,closed']);
        $conversation->update(['status' => $data['status']]);

        return back()->with('success', $data['status'] === 'closed' ? 'Conversation closed.' : 'Conversation reopened.');
    }

    /** Stop an account writing messages (what it wrote stays), or let it again. */
    public function block(Request $request, User $user)
    {
        $data = $request->validate(['blocked' => 'required|boolean']);
        try {
            $this->messaging->block($user, $request->user(), (bool) $data['blocked']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['blocked'] ? $user->name.' can no longer send messages.' : $user->name.' can send messages again.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

/**
 * Admin → Messages: every order thread, unread first.
 */
class MessageInboxController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('show', 'unread');

        $conversations = Conversation::with(['order', 'customer', 'seller'])
            ->when($filter === 'unread', fn ($q) => $q->where('admin_unread_count', '>', 0))
            ->when($filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->orderByDesc('admin_unread_count')
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.messages.index', ['conversations' => $conversations, 'filter' => $filter, 'unread' => Conversation::where('admin_unread_count', '>', 0)->count()]);
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
}

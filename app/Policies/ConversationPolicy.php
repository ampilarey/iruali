<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Only the customer, the shop and admins can read a thread; replies also need it to be open.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->roleOf($user) !== null;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        $role = $conversation->roleOf($user);

        return $role === 'admin' || ($role !== null && $conversation->isOpen());
    }
}

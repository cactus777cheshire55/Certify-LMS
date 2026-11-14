<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiChatConversation;
use App\Models\User;

class AiChatConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStudent() && $user->isInProgress();
    }

    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $user->isStudent()
            && $user->isInProgress()
            && $conversation->user_id === $user->id;
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function sendMessage(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
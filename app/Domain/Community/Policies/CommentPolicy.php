<?php

declare(strict_types=1);

namespace App\Domain\Community\Policies;

use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }
        if ($user->isReader()) {
            return true;
        }

        return $user->isAdministrator();
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return $comment->isEditableBy($user);
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return $comment->isEditableBy($user);
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $user->isAdministrator();
    }
}

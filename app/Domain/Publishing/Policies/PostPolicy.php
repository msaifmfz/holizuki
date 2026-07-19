<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Post $post): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->isAdministrator();
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isAdministrator();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->isAdministrator();
    }
}

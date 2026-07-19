<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Taxonomy\Models\Tag;

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

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Category $category): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isAdministrator();
    }
}

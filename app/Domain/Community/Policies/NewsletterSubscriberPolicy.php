<?php

declare(strict_types=1);

namespace App\Domain\Community\Policies;

use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Identity\Models\User;

class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->isAdministrator();
    }
}

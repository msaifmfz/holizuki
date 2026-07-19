<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Inbox\Models\ContactSubmission;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, ContactSubmission $contactSubmission): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, ContactSubmission $contactSubmission): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, ContactSubmission $contactSubmission): bool
    {
        return $user->isAdministrator();
    }
}

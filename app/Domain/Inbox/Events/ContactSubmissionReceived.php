<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Events;

use App\Domain\Inbox\Models\ContactSubmission;

final readonly class ContactSubmissionReceived
{
    public function __construct(public ContactSubmission $submission) {}
}

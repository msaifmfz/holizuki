<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Inbox\Events\ContactSubmissionReceived;
use App\Domain\Inbox\Models\ContactSubmission;

class ReceiveContactSubmission
{
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes): ContactSubmission
    {
        $submission = ContactSubmission::create($attributes);

        event(new ContactSubmissionReceived($submission));

        return $submission;
    }
}

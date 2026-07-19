<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use Override;

class SchedulePostRequest extends PublishPostRequest
{
    /** @return array<string, mixed> */
    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}

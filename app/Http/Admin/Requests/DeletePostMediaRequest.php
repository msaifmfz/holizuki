<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Http\AuthenticatedRequest;

final class DeletePostMediaRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'discard_story_writing' => ['sometimes', 'boolean'],
        ];
    }
}

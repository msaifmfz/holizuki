<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

final class UpdatePostMediaRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'role' => ['sometimes', Rule::enum(PostMediaRole::class)],
            'placement' => ['sometimes', Rule::enum(PostMediaPlacement::class)],
            'story_priority' => ['sometimes', Rule::enum(PostMediaStoryPriority::class)],
            'story_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Publishing\Enums\AuthoringJourneyKind;
use App\Domain\Publishing\Enums\AuthoringJourneyStep;
use App\Domain\Publishing\Enums\AuthoringPersonality;
use App\Domain\Publishing\Enums\AuthoringSurface;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class UpdatePostAuthoringJourneyRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['sometimes', Rule::enum(AuthoringJourneyKind::class)],
            'current_step' => ['sometimes', Rule::enum(AuthoringJourneyStep::class)],
            'completed_steps' => ['sometimes', 'array', 'max:6'],
            'completed_steps.*' => ['required', 'distinct', Rule::enum(AuthoringJourneyStep::class)],
            'brief' => ['sometimes', 'array', 'max:20'],
            'personality' => ['sometimes', Rule::enum(AuthoringPersonality::class)],
            'custom_instructions' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'web_research_enabled' => ['sometimes', 'boolean'],
            'last_surface' => ['sometimes', Rule::enum(AuthoringSurface::class)],
            // Opt in to carrying this post's voice over to future posts.
            'save_as_default' => ['sometimes', 'boolean'],
        ];
    }
}

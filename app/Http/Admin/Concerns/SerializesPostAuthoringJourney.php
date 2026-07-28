<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Publishing\Models\PostAuthoringJourney;
use stdClass;

trait SerializesPostAuthoringJourney
{
    /**
     * @return array{
     *     kind: string,
     *     current_step: string,
     *     completed_steps: list<string>,
     *     brief: array<string, mixed>|stdClass,
     *     personality: string,
     *     custom_instructions: string|null,
     *     web_research_enabled: bool,
     *     last_surface: string
     * }
     */
    private function postAuthoringJourneyData(PostAuthoringJourney $journey): array
    {
        return [
            'kind' => $journey->kind->value,
            'current_step' => $journey->current_step->value,
            'completed_steps' => $journey->completed_steps,
            'brief' => $journey->brief === [] ? new stdClass : $journey->brief,
            'personality' => $journey->personality->value,
            'custom_instructions' => $journey->custom_instructions,
            'web_research_enabled' => $journey->web_research_enabled,
            'last_surface' => $journey->last_surface->value,
        ];
    }
}

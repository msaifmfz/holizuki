<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Prompts\AuthoringDraftPrompt;
use App\Domain\Assistant\Prompts\AuthoringImageGuidancePrompt;
use App\Domain\Assistant\Prompts\AuthoringImagePassagesPrompt;
use App\Domain\Assistant\Prompts\AuthoringPlansPrompt;
use App\Domain\Assistant\Prompts\AuthoringResearchPrompt;
use LogicException;

final readonly class AuthoringPromptFactory
{
    public function __construct(
        private AuthoringPlansPrompt $plans,
        private AuthoringImageGuidancePrompt $imageGuidance,
        private AuthoringImagePassagesPrompt $imagePassages,
        private AuthoringDraftPrompt $draft,
        private AuthoringResearchPrompt $research,
    ) {}

    public function build(AuthoringTask $task): string
    {
        return match ($task->kind) {
            AuthoringTaskKind::Plans => $this->plans->build($task),
            AuthoringTaskKind::ImageGuidance => $this->imageGuidance->build($task),
            AuthoringTaskKind::ImagePassages => $this->imagePassages->build($task),
            AuthoringTaskKind::Draft => $this->draft->build($task, $this->plan($task)),
            AuthoringTaskKind::Research => $this->research->build($task),
        };
    }

    private function plan(AuthoringTask $task): AuthoringArtifact
    {
        $planId = $task->context['plan_artifact_id'] ?? null;

        if (! is_int($planId)) {
            throw new LogicException('A draft task requires a plan artifact.');
        }

        return AuthoringArtifact::query()
            ->whereKey($planId)
            ->where('post_id', $task->post_id)
            ->firstOrFail();
    }
}

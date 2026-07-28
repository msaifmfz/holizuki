<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Publishing\Models\Post;

trait SerializesAuthoringArtifacts
{
    /** @return array<string, mixed> */
    private function authoringTaskData(AuthoringTask $task, Post $post): array
    {
        return [
            'id' => $task->public_id,
            'kind' => $task->kind->value,
            'status' => $task->status->value,
            'base_lock_version' => $task->base_lock_version,
            'stale' => $task->base_lock_version !== $post->lock_version,
            'target_id' => $this->authoringTaskTargetId($task),
            'attempts' => $task->attempts,
            'error' => $task->error_code === null ? null : [
                'code' => $task->error_code,
                'message' => $task->error_message,
            ],
            'started_at' => $task->started_at?->toISOString(),
            'finished_at' => $task->finished_at?->toISOString(),
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    private function authoringTaskTargetId(AuthoringTask $task): ?string
    {
        $contextKey = match ($task->kind) {
            AuthoringTaskKind::ImageGuidance,
            AuthoringTaskKind::Draft => 'plan_id',
            AuthoringTaskKind::ImagePassages => 'composition_public_id',
            default => null,
        };
        $targetId = $contextKey === null ? null : ($task->context[$contextKey] ?? null);

        return is_string($targetId) ? $targetId : null;
    }

    /** @return array<string, mixed> */
    private function authoringArtifactData(
        AuthoringArtifact $artifact,
        Post $post,
        ?AuthoringSelection $selection,
        ?bool $stale = null,
    ): array {
        return [
            'id' => $artifact->public_id,
            'type' => $artifact->type->value,
            'group_id' => $artifact->group_id,
            'parent_id' => $artifact->parent?->public_id,
            'position' => $artifact->position,
            'base_lock_version' => $artifact->base_lock_version,
            'stale' => $stale ?? $artifact->isStaleFor($post),
            'selected' => $selection?->selected_plan_id === $artifact->id
                || $selection?->selected_draft_id === $artifact->id,
            'payload' => $artifact->payload,
            'created_at' => $artifact->created_at?->toISOString(),
        ];
    }

    /** @return array{plan_id: string|null, draft_id: string|null} */
    private function authoringSelectionData(?AuthoringSelection $selection): array
    {
        $selection?->loadMissing(['selectedPlan', 'selectedDraft']);

        return [
            'plan_id' => $selection?->selectedPlan?->public_id,
            'draft_id' => $selection?->selectedDraft?->public_id,
        ];
    }
}

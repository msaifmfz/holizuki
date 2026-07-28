<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Normalizers\AuthoringDraftNormalizer;
use App\Domain\Assistant\Normalizers\AuthoringImageGuidanceNormalizer;
use App\Domain\Assistant\Normalizers\AuthoringImagePassagesNormalizer;
use App\Domain\Assistant\Normalizers\AuthoringOutputNormalizer;
use App\Domain\Assistant\Normalizers\AuthoringPlansNormalizer;
use App\Domain\Assistant\Normalizers\AuthoringResearchNormalizer;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Persists the artifacts a finished authoring task produced.
 *
 * Validation of the agent's output belongs to the per-kind normalizers, and
 * runs before the transaction opens so a rejected output never holds a row
 * lock. This action only decides whether the task may still store anything.
 */
final readonly class StoreAuthoringArtifacts
{
    public function __construct(
        private AuthoringPlansNormalizer $plans,
        private AuthoringImageGuidanceNormalizer $imageGuidance,
        private AuthoringImagePassagesNormalizer $imagePassages,
        private AuthoringDraftNormalizer $draft,
        private AuthoringResearchNormalizer $research,
    ) {}

    /**
     * @param  array<string, mixed>  $output
     * @return list<AuthoringArtifact>
     */
    public function handle(AuthoringTask $task, Post $post, array $output): array
    {
        $payloads = $this->normalizerFor($task->kind)->normalize(
            $task,
            $post,
            AssistantOutput::fromArray($output),
        );

        return DB::transaction(function () use ($task, $post, $payloads): array {
            /** @var AuthoringTask $lockedTask */
            $lockedTask = AuthoringTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $existing = array_values($lockedTask->artifacts()->oldest('id')->get()->all());

            // A retry of an already-stored task is a no-op, not a duplicate.
            if ($lockedTask->status === AuthoringTaskStatus::Completed && $existing !== []) {
                return $existing;
            }

            if ($lockedTask->status !== AuthoringTaskStatus::Running) {
                throw new LogicException('Only a running authoring task may store artifacts.');
            }

            $artifacts = [];

            foreach ($payloads as $payload) {
                $artifacts[] = AuthoringArtifact::create([
                    'public_id' => (string) Str::uuid(),
                    'post_id' => $post->id,
                    'assistant_authoring_task_id' => $lockedTask->id,
                    'parent_id' => $payload->parentId,
                    'type' => $payload->type,
                    'group_id' => $lockedTask->public_id,
                    'position' => $payload->position,
                    'base_lock_version' => $lockedTask->base_lock_version,
                    'payload' => $payload->payload,
                ]);
            }

            $lockedTask->forceFill([
                'status' => AuthoringTaskStatus::Completed,
                'error_code' => null,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
            $task->setRawAttributes($lockedTask->getAttributes(), true);

            return $artifacts;
        });
    }

    private function normalizerFor(AuthoringTaskKind $kind): AuthoringOutputNormalizer
    {
        return match ($kind) {
            AuthoringTaskKind::Plans => $this->plans,
            AuthoringTaskKind::ImageGuidance => $this->imageGuidance,
            AuthoringTaskKind::ImagePassages => $this->imagePassages,
            AuthoringTaskKind::Draft => $this->draft,
            AuthoringTaskKind::Research => $this->research,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Assistant\Actions\RequestAuthoringTask;
use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Exceptions\StaleAuthoringArtifactException;
use App\Domain\Assistant\Jobs\RunAuthoringTask;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The shared shape of every "ask the assistant for something" endpoint:
 * confirm the assistant is free, hash the inputs, claim the single active task
 * slot for the post, and queue the run. Controllers keep only the preconditions
 * that are specific to what they are asking for.
 */
trait DispatchesAuthoringTasks
{
    use SerializesAuthoringArtifacts;

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchAuthoringTask(
        Post $post,
        User $requester,
        AuthoringTaskKind $kind,
        string $requestId,
        array $context,
        AssistantAvailability $availability,
        AuthoringContextFactory $contexts,
        RequestAuthoringTask $requestTask,
    ): JsonResponse {
        $availability->ensureCanStart();

        $result = $requestTask->handle(
            $post,
            $requester,
            $kind,
            $requestId,
            $contexts->hash($kind, $context),
            $context,
        );

        if ($result->shouldDispatch) {
            // The job already defers itself until after the surrounding
            // transaction commits.
            dispatch(new RunAuthoringTask($result->task->id, $post->id));
        }

        return response()->json([
            'task' => $this->authoringTaskData($result->task, $post),
            'cached' => $result->cached,
            'resumed' => $result->resumed,
        ], $result->task->status->isActive() ? 202 : 200);
    }

    /**
     * The plan an artifact must belong to before further work is allowed: it
     * has to be this post's, still current, and the one the author picked.
     *
     * Every caller reports the same 422 so the editor can handle one shape.
     */
    private function ensureCurrentSelectedPlan(
        Post $post,
        AuthoringArtifact $plan,
        AuthoringArtifactFreshness $freshness,
        string $message,
    ): void {
        abort_unless(
            $plan->post_id === $post->id && $plan->type === AuthoringArtifactType::Plan,
            404,
        );

        if ($freshness->isStale($post, $plan)) {
            throw new StaleAuthoringArtifactException;
        }

        $selectedPlanId = AuthoringSelection::query()
            ->whereBelongsTo($post)
            ->value('selected_plan_id');

        if ($selectedPlanId !== $plan->id) {
            throw ValidationException::withMessages(['plan' => $message]);
        }
    }
}

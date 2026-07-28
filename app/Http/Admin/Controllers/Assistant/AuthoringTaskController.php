<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringContextCache;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesAuthoringArtifacts;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class AuthoringTaskController extends Controller
{
    use SerializesAuthoringArtifacts;

    public function __construct(
        private readonly AuthoringArtifactFreshness $freshness,
        private readonly AuthoringContextCache $contextCache,
    ) {}

    public function show(Post $post, AuthoringTask $task): JsonResponse
    {
        Gate::authorize('update', $post);
        abort_unless($task->post_id === $post->id, 404);

        return $this->contextCache->during(fn (): JsonResponse => $this->taskWithArtifacts($post, $task));
    }

    private function taskWithArtifacts(Post $post, AuthoringTask $task): JsonResponse
    {
        $selection = AuthoringSelection::query()->whereBelongsTo($post)->first();

        return response()->json([
            'task' => $this->authoringTaskData($task, $post),
            'artifacts' => $task->artifacts()
                ->with('parent')
                ->oldest('id')
                ->get()
                ->map(fn (AuthoringArtifact $artifact): array => $this->authoringArtifactData(
                    $artifact,
                    $post,
                    $selection,
                    $this->freshness->isStale($post, $artifact),
                )),
        ]);
    }
}

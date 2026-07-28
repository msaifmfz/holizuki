<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringArtifactIndex;
use App\Domain\Assistant\Services\AuthoringContextCache;
use App\Domain\Assistant\Services\AuthoringImageFlowReadiness;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesAuthoringArtifacts;
use App\Http\Admin\Concerns\SerializesAuthoringImageCompositions;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class AuthoringArtifactController extends Controller
{
    use SerializesAuthoringArtifacts;
    use SerializesAuthoringImageCompositions;

    public function __construct(
        private readonly AuthoringArtifactFreshness $freshness,
        private readonly AuthoringArtifactIndex $artifactIndex,
        private readonly AuthoringImageFlowReadiness $imageFlowReadiness,
        private readonly AuthoringContextCache $contextCache,
    ) {}

    public function index(Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        // The editor polls this while a task runs. Every artifact and every
        // photo composition is staleness-checked against a rebuilt context, so
        // memoize those builds for this read instead of repeating them.
        return $this->contextCache->during(fn (): JsonResponse => $this->listing($post));
    }

    private function listing(Post $post): JsonResponse
    {
        $selection = AuthoringSelection::query()->whereBelongsTo($post)->first();
        $artifacts = $this->artifactIndex->for($post, $selection);

        return response()->json([
            'tasks' => AuthoringTask::query()
                ->whereBelongsTo($post)
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (AuthoringTask $task): array => $this->authoringTaskData($task, $post)),
            'plans' => $this->serializeArtifacts($artifacts['plans'], $post, $selection),
            'drafts' => $this->serializeArtifacts($artifacts['drafts'], $post, $selection),
            'research_findings' => $this->serializeArtifacts(
                $artifacts['research_findings'],
                $post,
                $selection,
            ),
            'image_guidance' => $this->serializeArtifacts(
                $artifacts['image_guidance'],
                $post,
                $selection,
            ),
            'image_group_suggestions' => $this->serializeArtifacts(
                $artifacts['image_group_suggestions'],
                $post,
                $selection,
            ),
            'image_passages' => $this->serializeArtifacts(
                $artifacts['image_passages'],
                $post,
                $selection,
            ),
            'image_compositions' => $this->imageCompositions(
                $post,
                $artifacts['image_compositions'],
            ),
            'selection' => $this->authoringSelectionData($selection),
        ]);
    }

    /**
     * @param  Collection<int, AuthoringArtifact>  $artifacts
     * @return Collection<int, array<string, mixed>>
     */
    private function serializeArtifacts(
        Collection $artifacts,
        Post $post,
        ?AuthoringSelection $selection,
    ): Collection {
        return $artifacts
            ->sortBy([['assistant_authoring_task_id', 'desc'], ['position', 'asc']])
            ->values()
            ->map(fn (AuthoringArtifact $artifact): array => $this->authoringArtifactData(
                $artifact,
                $post,
                $selection,
                $this->freshness->isStale($post, $artifact),
            ));
    }

    /**
     * @param  EloquentCollection<int, AuthoringImageComposition>  $compositions
     * @return list<array<string, mixed>>
     */
    private function imageCompositions(
        Post $post,
        EloquentCollection $compositions,
    ): array {
        $serialized = [];

        foreach ($compositions as $composition) {
            $serialized[] = $this->authoringImageCompositionData(
                $composition,
                $this->imageFlowReadiness->passageIsStale($post, $composition),
            );
        }

        return $serialized;
    }
}

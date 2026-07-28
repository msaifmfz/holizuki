<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\RequestAuthoringTask;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\DispatchesAuthoringTasks;
use App\Http\Admin\Requests\Assistant\GenerateAuthoringImagePassagesRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

final class AuthoringImagePassageController extends Controller
{
    use DispatchesAuthoringTasks;

    public function store(
        GenerateAuthoringImagePassagesRequest $request,
        Post $post,
        AuthoringImageComposition $composition,
        AssistantAvailability $availability,
        AuthoringArtifactFreshness $freshness,
        AuthoringContextFactory $contexts,
        RequestAuthoringTask $requestTask,
    ): JsonResponse {
        abort_unless(
            $composition->post_id === $post->id
            && $composition->usage === AuthoringImageCompositionUsage::Include
            && $composition->media()->exists(),
            404,
        );
        $this->ensureCurrentSelectedPlan(
            $post,
            $composition->plan()->firstOrFail(),
            $freshness,
            'Choose this plan before drafting photo passages.',
        );

        return $this->dispatchAuthoringTask(
            $post,
            $request->authenticatedUser(),
            AuthoringTaskKind::ImagePassages,
            $request->requestId(),
            $contexts->forImagePassages($post, $composition),
            $availability,
            $contexts,
            $requestTask,
        );
    }
}

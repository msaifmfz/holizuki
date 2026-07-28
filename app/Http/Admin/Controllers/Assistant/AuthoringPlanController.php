<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\RequestAuthoringTask;
use App\Domain\Assistant\Actions\SelectAuthoringArtifact;
use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\DispatchesAuthoringTasks;
use App\Http\Admin\Requests\Assistant\GenerateAuthoringPlansRequest;
use App\Http\Admin\Requests\Assistant\SelectAuthoringArtifactRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

final class AuthoringPlanController extends Controller
{
    use DispatchesAuthoringTasks;

    public function store(
        GenerateAuthoringPlansRequest $request,
        Post $post,
        AssistantAvailability $availability,
        AuthoringContextFactory $contexts,
        RequestAuthoringTask $requestTask,
    ): JsonResponse {
        return $this->dispatchAuthoringTask(
            $post,
            $request->authenticatedUser(),
            AuthoringTaskKind::Plans,
            $request->requestId(),
            $contexts->forPlans($post),
            $availability,
            $contexts,
            $requestTask,
        );
    }

    public function select(
        SelectAuthoringArtifactRequest $request,
        Post $post,
        AuthoringArtifact $artifact,
        SelectAuthoringArtifact $selectArtifact,
    ): JsonResponse {
        abort_unless($artifact->post_id === $post->id && $artifact->type === AuthoringArtifactType::Plan, 404);
        $selection = $selectArtifact->handle(
            $post,
            $artifact,
            AuthoringArtifactType::Plan,
            $request->authenticatedUser(),
        );

        return response()->json(['selection' => $this->authoringSelectionData($selection)]);
    }
}

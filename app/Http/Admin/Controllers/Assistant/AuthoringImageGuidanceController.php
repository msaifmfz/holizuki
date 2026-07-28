<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\RequestAuthoringTask;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\DispatchesAuthoringTasks;
use App\Http\Admin\Requests\Assistant\GenerateAuthoringImageGuidanceRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

final class AuthoringImageGuidanceController extends Controller
{
    use DispatchesAuthoringTasks;

    public function store(
        GenerateAuthoringImageGuidanceRequest $request,
        Post $post,
        AuthoringArtifact $artifact,
        AssistantAvailability $availability,
        AuthoringArtifactFreshness $freshness,
        AuthoringContextFactory $contexts,
        RequestAuthoringTask $requestTask,
    ): JsonResponse {
        $this->ensureCurrentSelectedPlan(
            $post,
            $artifact,
            $freshness,
            'Choose this plan before requesting photo guidance.',
        );

        return $this->dispatchAuthoringTask(
            $post,
            $request->authenticatedUser(),
            AuthoringTaskKind::ImageGuidance,
            $request->requestId(),
            $contexts->forImageGuidance($post, $artifact),
            $availability,
            $contexts,
            $requestTask,
        );
    }
}

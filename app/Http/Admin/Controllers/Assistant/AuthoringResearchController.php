<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\RequestAuthoringTask;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\DispatchesAuthoringTasks;
use App\Http\Admin\Requests\Assistant\GenerateAuthoringResearchRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

final class AuthoringResearchController extends Controller
{
    use DispatchesAuthoringTasks;

    public function store(
        GenerateAuthoringResearchRequest $request,
        Post $post,
        AssistantAvailability $availability,
        AuthoringContextFactory $contexts,
        RequestAuthoringTask $requestTask,
    ): JsonResponse {
        return $this->dispatchAuthoringTask(
            $post,
            $request->authenticatedUser(),
            AuthoringTaskKind::Research,
            $request->requestId(),
            $contexts->forResearch($post),
            $availability,
            $contexts,
            $requestTask,
        );
    }
}

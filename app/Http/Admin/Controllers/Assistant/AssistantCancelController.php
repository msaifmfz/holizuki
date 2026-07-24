<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\RequestAssistantCancellation;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Publishing\Models\Post;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssistantCancelController extends Controller
{
    public function __construct(private readonly RequestAssistantCancellation $requestCancellation) {}

    public function __invoke(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        /** @var array{request_id?: string|null} $validated */
        $validated = $request->validate([
            'request_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        $turn = $this->requestCancellation->handle(
            $post,
            $validated['request_id'] ?? null,
        );

        return response()->json([
            'status' => $turn instanceof AssistantTurn
                ? AssistantSessionStatus::Cancelling->value
                : AssistantSessionStatus::Idle->value,
            'turn_id' => $turn?->id,
        ]);
    }
}

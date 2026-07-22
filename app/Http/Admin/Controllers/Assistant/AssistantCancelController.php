<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Publishing\Models\Post;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Marks the running turn cancelled and frees the session. The agent process
 * itself dies when its SSE connection notices the client is gone; this
 * endpoint recovers a session stuck in the running state.
 */
class AssistantCancelController extends Controller
{
    public function __construct(private readonly FailAssistantTurn $failTurn) {}

    public function __invoke(Request $request, Post $post): JsonResponse
    {
        abort_unless(config()->boolean('assistant.enabled', true), 404);
        Gate::authorize('update', $post);

        $session = AssistantSession::query()->where('post_id', $post->id)->first();

        $runningTurn = $session?->turns()
            ->where('status', AssistantTurnStatus::Running)
            ->latest('id')
            ->first();

        if ($runningTurn !== null) {
            $this->failTurn->handle($runningTurn, 'Cancelled by the author.', AssistantTurnStatus::Cancelled);
        } elseif ($session !== null) {
            $session->forceFill([
                'status' => AssistantSessionStatus::Idle,
                'turn_started_at' => null,
            ])->save();
        }

        return response()->json(['status' => AssistantSessionStatus::Idle->value]);
    }
}

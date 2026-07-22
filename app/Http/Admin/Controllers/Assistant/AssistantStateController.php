<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesAssistantChanges;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Hydrates the assistant panel on page load: session status, the pending
 * proposals awaiting a decision, and the recent conversation.
 */
class AssistantStateController extends Controller
{
    use SerializesAssistantChanges;

    public function __invoke(Request $request, Post $post): JsonResponse
    {
        abort_unless(config()->boolean('assistant.enabled', true), 404);
        Gate::authorize('update', $post);

        $session = AssistantSession::query()->where('post_id', $post->id)->first();

        $changes = AssistantChange::query()
            ->where('post_id', $post->id)
            ->pending()
            ->orderBy('id')
            ->get()
            ->map(fn (AssistantChange $change): array => $this->assistantChangeData($change))
            ->values();

        $turns = $session === null
            ? collect()
            : $session->turns()
                ->latest('id')
                ->limit(30)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (AssistantTurn $turn): array => [
                    'id' => $turn->id,
                    'task_type' => $turn->task_type->value,
                    'status' => $turn->status->value,
                    'user_prompt' => $turn->user_prompt,
                    'assistant_message' => $turn->assistant_message,
                    'error' => $turn->error,
                    'context' => $turn->context,
                    'created_at' => (string) $turn->created_at?->toISOString(),
                ]);

        return response()->json([
            'session' => [
                'status' => $this->effectiveStatus($session)->value,
            ],
            'changes' => $changes,
            'turns' => $turns,
        ]);
    }

    /**
     * A session whose running turn outlived the timeout was abandoned (a dead
     * SSE connection that never cleaned up); report it idle so the panel is
     * usable without waiting for the next turn to reclaim it. The row itself
     * is reclaimed lazily by StartAssistantTurn.
     */
    private function effectiveStatus(?AssistantSession $session): AssistantSessionStatus
    {
        if (! $session instanceof AssistantSession || $session->status !== AssistantSessionStatus::Running) {
            return AssistantSessionStatus::Idle;
        }

        $timeout = config()->integer('assistant.turn_timeout', 300);
        $stillRunning = $session->turn_started_at !== null
            && $session->turn_started_at->gt(now()->subSeconds($timeout));

        return $stillRunning ? AssistantSessionStatus::Running : AssistantSessionStatus::Idle;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesAssistantChanges;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Hydrates the assistant panel with the canonical runtime, conversation, and
 * pending-proposal state.
 */
class AssistantStateController extends Controller
{
    use SerializesAssistantChanges;

    public function __construct(
        private readonly AssistantAvailability $availability,
        private readonly AssistantRunLease $runLease,
    ) {}

    public function __invoke(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $session = AssistantSession::query()->where('post_id', $post->id)->first();
        $activeTurn = $this->activeTurn($session);
        $availability = $this->availability->state();

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
                    'error' => $turn->error_message,
                    'context' => $turn->context,
                    'created_at' => (string) $turn->created_at?->toISOString(),
                ]);

        return response()->json([
            'enabled' => config()->boolean('assistant.enabled', true),
            'available' => $availability['available'],
            'status' => $this->effectiveStatus($availability['status'], $session, $activeTurn),
            'retry_at' => $availability['retry_at'],
            'active_turn' => $activeTurn instanceof AssistantTurn ? [
                'id' => $activeTurn->id,
                'task_type' => $activeTurn->task_type->value,
                'started_at' => $session?->turn_started_at?->toISOString(),
            ] : null,
            'outline_ready' => $session?->outline_ready === true
                && is_string($session->outline_markdown)
                && trim($session->outline_markdown) !== '',
            'changes' => $changes,
            'turns' => $turns,
        ]);
    }

    /**
     * Stale rows are reclaimed by StartAssistantTurn. Reporting them as idle
     * here lets the browser recover without mutating state from a GET request.
     */
    private function activeTurn(?AssistantSession $session): ?AssistantTurn
    {
        if (
            ! $session instanceof AssistantSession
            || $session->status === AssistantSessionStatus::Idle
            || $session->active_run_token === null
            || $session->turn_started_at === null
        ) {
            return null;
        }

        $timeout = $this->runLease->durationSeconds();

        if ($session->turn_started_at->lte(now()->subSeconds($timeout))) {
            return null;
        }

        return $session->turns()
            ->where('run_token', $session->active_run_token)
            ->whereIn('status', ['running', 'cancelling'])
            ->first();
    }

    private function effectiveStatus(
        string $availabilityStatus,
        ?AssistantSession $session,
        ?AssistantTurn $activeTurn,
    ): string {
        if ($activeTurn instanceof AssistantTurn && $session instanceof AssistantSession) {
            return $session->status->value;
        }

        return $availabilityStatus === 'available'
            ? AssistantSessionStatus::Idle->value
            : $availabilityStatus;
    }
}

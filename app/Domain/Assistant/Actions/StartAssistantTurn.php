<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Opens an agent turn: enforces one running turn per post, re-materializes
 * the workspace from the live post, and snapshots it so the completing diff
 * has a stable base.
 */
class StartAssistantTurn
{
    public function __construct(
        private readonly WorkspaceManager $workspace,
        private readonly FailAssistantTurn $failTurn,
    ) {}

    /** @param array<string, mixed> $context */
    public function handle(Post $post, AssistantTaskType $taskType, string $prompt, array $context = []): AssistantTurn
    {
        $turn = DB::transaction(function () use ($post, $taskType, $prompt, $context): AssistantTurn {
            $session = AssistantSession::query()->firstOrCreate(
                ['post_id' => $post->id],
                ['claude_session_id' => (string) Str::uuid(), 'status' => AssistantSessionStatus::Idle],
            );

            /** @var AssistantSession $session */
            $session = AssistantSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->status === AssistantSessionStatus::Running) {
                $timeout = config()->integer('assistant.turn_timeout', 300);

                if ($session->turn_started_at !== null && $session->turn_started_at->gt(now()->subSeconds($timeout))) {
                    throw AssistantBusyException::forPost();
                }

                $session->turns()
                    ->where('status', AssistantTurnStatus::Running)
                    ->update(['status' => AssistantTurnStatus::Failed, 'error' => 'The turn was abandoned.']);
            }

            $session->forceFill([
                'status' => AssistantSessionStatus::Running,
                'turn_started_at' => now(),
            ])->save();

            return $session->turns()->create([
                'task_type' => $taskType,
                'status' => AssistantTurnStatus::Running,
                'user_prompt' => $prompt,
                'context' => $context === [] ? null : $context,
            ]);
        });

        // Materialization runs after the commit that already marked the
        // session running; if it throws, release the turn so a disk/permission
        // hiccup can't wedge the session as busy until the timeout.
        try {
            $this->workspace->materialize($post);

            $turn->forceFill([
                'snapshot_draft' => $this->workspace->readDraft($post),
                'snapshot_meta' => $this->workspace->readMeta($post),
            ])->save();
        } catch (Throwable $exception) {
            $this->failTurn->handle($turn, 'The workspace could not be prepared. Try again.');

            throw $exception;
        }

        return $turn;
    }
}

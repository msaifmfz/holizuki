<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Exceptions\DuplicateAssistantRequestException;
use App\Domain\Assistant\Exceptions\OutlineNotReadyException;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\AssistantWorkspaceLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
        private readonly AssistantRunLease $runLease,
        private readonly AssistantWorkspaceLease $workspaceLease,
    ) {}

    /** @param array<string, mixed> $context */
    public function handle(
        Post $post,
        AssistantTaskType $taskType,
        string $prompt,
        string $requestId,
        array $context = [],
    ): AssistantTurn {
        if (! Str::isUuid($requestId)) {
            throw new InvalidArgumentException('Assistant request IDs must be UUIDs.');
        }

        $existing = AssistantTurn::query()->where('request_id', $requestId)->first();

        if ($existing !== null) {
            throw DuplicateAssistantRequestException::forTurn($existing);
        }

        if (
            $taskType === AssistantTaskType::Outline
            && ($context['step'] ?? null) === 'draft'
            && ! AssistantSession::query()
                ->where('post_id', $post->id)
                ->where('outline_ready', true)
                ->whereNotNull('outline_markdown')
                ->exists()
        ) {
            throw new OutlineNotReadyException;
        }

        $runToken = (string) Str::uuid();

        if (! $this->runLease->acquire($runToken)) {
            throw AssistantBusyException::globally();
        }

        try {
            $turn = $this->createTurn($post, $taskType, $prompt, $requestId, $runToken, $context);
            $workspacePost = $this->workspacePost($post);

            if (! $this->workspaceLease->acquire($workspacePost, $runToken)) {
                throw AssistantBusyException::forPost();
            }

            try {
                $this->workspace->materialize($workspacePost, $turn);

                $turn->forceFill([
                    'snapshot_draft' => $this->workspace->readDraft($workspacePost, $turn),
                    'snapshot_meta' => $this->workspace->readMeta($workspacePost, $turn),
                ])->save();
            } finally {
                $this->workspaceLease->release($workspacePost, $runToken);
            }
        } catch (Throwable $exception) {
            try {
                if (isset($turn)) {
                    $this->failTurn->handle(
                        $turn,
                        'The workspace could not be prepared. Try again.',
                        errorCode: 'workspace_preparation_failed',
                    );

                    try {
                        $this->workspace->destroyRun($post, $runToken);
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                    }
                }
            } finally {
                $this->runLease->release($runToken);
            }

            throw $exception;
        }

        return $turn;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createTurn(
        Post $post,
        AssistantTaskType $taskType,
        string $prompt,
        string $requestId,
        string $runToken,
        array $context,
    ): AssistantTurn {
        try {
            return DB::transaction(function () use ($post, $taskType, $prompt, $requestId, $runToken, $context): AssistantTurn {
                $duplicate = AssistantTurn::query()->where('request_id', $requestId)->first();

                if ($duplicate !== null) {
                    throw DuplicateAssistantRequestException::forTurn($duplicate);
                }

                $session = AssistantSession::query()->firstOrCreate(
                    ['post_id' => $post->id],
                    [
                        'claude_session_id' => (string) Str::uuid(),
                        'status' => AssistantSessionStatus::Idle,
                    ],
                );

                /** @var AssistantSession $session */
                $session = AssistantSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                $this->expireAbandonedTurn($session);
                $this->prepareConversation($session, $taskType);

                $session->forceFill([
                    'status' => AssistantSessionStatus::Running,
                    'active_run_token' => $runToken,
                    'turn_started_at' => now(),
                ])->save();

                return $session->turns()->create([
                    'run_token' => $runToken,
                    'request_id' => $requestId,
                    'task_type' => $taskType,
                    'status' => AssistantTurnStatus::Running,
                    'user_prompt' => $prompt,
                    'context' => $context === [] ? null : $context,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $duplicate = AssistantTurn::query()->where('request_id', $requestId)->first();

            if ($duplicate !== null) {
                throw DuplicateAssistantRequestException::forTurn($duplicate);
            }

            throw $exception;
        }
    }

    private function workspacePost(Post $post): Post
    {
        return DB::transaction(
            fn (): Post => Post::query()
                ->withTrashed()
                ->with(['category', 'media', 'tags'])
                ->whereKey($post->id)
                ->lockForUpdate()
                ->firstOrFail(),
        );
    }

    private function expireAbandonedTurn(AssistantSession $session): void
    {
        if ($session->status === AssistantSessionStatus::Idle) {
            return;
        }

        $timeout = $this->runLease->durationSeconds();

        if ($session->turn_started_at !== null && $session->turn_started_at->gt(now()->subSeconds($timeout))) {
            throw AssistantBusyException::forPost();
        }

        $activeTurn = $session->turns()
            ->where('run_token', $session->active_run_token)
            ->lockForUpdate()
            ->first();

        if ($activeTurn !== null && $activeTurn->status->isActive()) {
            $activeTurn->forceFill([
                'status' => AssistantTurnStatus::Failed,
                'error_code' => 'turn_abandoned',
                'error_message' => 'The assistant turn exceeded its execution lease.',
                'finished_at' => now(),
            ])->save();

            if ($activeTurn->task_type->usesPersistentSession()) {
                $session->forceFill([
                    'claude_session_id' => (string) Str::uuid(),
                    'resume_ready' => false,
                    'conversation_model' => null,
                ]);
            }
        }
    }

    private function prepareConversation(AssistantSession $session, AssistantTaskType $taskType): void
    {
        if (! $taskType->usesPersistentSession()) {
            return;
        }

        $model = $taskType->model();

        if ($session->conversation_model !== null && $session->conversation_model !== $model) {
            $session->claude_session_id = (string) Str::uuid();
            $session->resume_ready = false;
        }

        $session->conversation_model = $model;
    }
}

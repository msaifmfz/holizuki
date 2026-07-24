<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\AssistantWorkspaceLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PruneAssistantData
{
    /** @var list<string> */
    private array $prunedClaudeSessionIds = [];

    /** @var list<string> */
    private array $prunedWorkspaceRoots = [];

    public function __construct(
        private readonly Filesystem $files,
        private readonly WorkspaceManager $workspace,
        private readonly AssistantRunLease $runLease,
        private readonly AssistantWorkspaceLease $workspaceLease,
    ) {}

    public function handle(): int
    {
        $maintenanceToken = (string) Str::uuid();

        if (! $this->runLease->acquire($maintenanceToken)) {
            return 0;
        }

        try {
            $this->prunedClaudeSessionIds = [];
            $this->prunedWorkspaceRoots = [];
            $cutoff = now()->subDays(config()->integer('assistant.prune_after_days', 30));
            $pruned = $this->pruneTurns($cutoff, $maintenanceToken);
            $pruned += $this->pruneSessions($cutoff, $maintenanceToken);

            return $pruned + $this->pruneOrphanedWorkspaces($maintenanceToken);
        } finally {
            $this->runLease->release($maintenanceToken);
        }
    }

    /** @return list<string> */
    public function prunedClaudeSessionIds(): array
    {
        return $this->prunedClaudeSessionIds;
    }

    /** @return list<string> */
    public function prunedWorkspaceRoots(): array
    {
        return $this->prunedWorkspaceRoots;
    }

    private function pruneTurns(CarbonInterface $cutoff, string $maintenanceToken): int
    {
        $pruned = 0;

        foreach (
            AssistantTurn::query()
                ->where('created_at', '<', $cutoff)
                ->whereNotIn('status', [AssistantTurnStatus::Running, AssistantTurnStatus::Cancelling])
                ->whereDoesntHave('changes', fn (Builder $query) => $query->where('status', AssistantChangeStatus::Proposed))
                ->with('session:id,post_id')
                ->lazyById() as $candidate
        ) {
            $session = $candidate->session;
            if ($session === null) {
                continue;
            }
            if (! $this->workspaceLease->acquire($session->post_id, $maintenanceToken)) {
                continue;
            }

            try {
                $deleted = DB::transaction(function () use ($candidate, $cutoff): ?array {
                    /** @var AssistantSession|null $lockedSession */
                    $lockedSession = AssistantSession::query()
                        ->whereKey($candidate->assistant_session_id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedSession === null) {
                        return null;
                    }

                    /** @var AssistantTurn|null $lockedTurn */
                    $lockedTurn = AssistantTurn::query()
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        $lockedTurn === null
                        || $lockedTurn->status->isActive()
                        || $lockedTurn->created_at?->gte($cutoff) === true
                        || $lockedTurn->changes()
                            ->where('status', AssistantChangeStatus::Proposed)
                            ->exists()
                    ) {
                        return null;
                    }

                    $runToken = $lockedTurn->run_token;
                    $postId = $lockedSession->post_id;
                    $lockedTurn->delete();

                    return [$postId, $runToken];
                });

                if (is_array($deleted)) {
                    [$postId, $runToken] = $deleted;
                    $this->workspace->destroyRun($this->postReference($postId), $runToken);
                    $pruned++;
                }
            } finally {
                $this->workspaceLease->release($session->post_id, $maintenanceToken);
            }
        }

        return $pruned;
    }

    private function pruneSessions(CarbonInterface $cutoff, string $maintenanceToken): int
    {
        $pruned = 0;

        foreach (
            AssistantSession::query()
                ->where('updated_at', '<', $cutoff)
                ->where('status', AssistantSessionStatus::Idle)
                ->whereNull('active_run_token')
                ->whereDoesntHave('turns')
                ->lazyById() as $candidate
        ) {
            if (! $this->workspaceLease->acquire($candidate->post_id, $maintenanceToken)) {
                continue;
            }

            try {
                $deleted = DB::transaction(function () use ($candidate, $cutoff): ?array {
                    /** @var AssistantSession|null $session */
                    $session = AssistantSession::query()
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        $session === null
                        || $session->status !== AssistantSessionStatus::Idle
                        || $session->active_run_token !== null
                        || $session->updated_at?->gte($cutoff) === true
                        || $session->turns()->exists()
                    ) {
                        return null;
                    }

                    $details = [$session->post_id, $session->claude_session_id];
                    $session->delete();

                    return $details;
                });

                if (is_array($deleted)) {
                    [$postId, $claudeSessionId] = $deleted;
                    $post = $this->postReference($postId);
                    $root = $this->workspace->rootFor($post);
                    $this->workspace->destroy($post);
                    $this->prunedClaudeSessionIds[] = $claudeSessionId;
                    $this->prunedWorkspaceRoots[] = $root;
                    $pruned++;
                }
            } finally {
                $this->workspaceLease->release($candidate->post_id, $maintenanceToken);
            }
        }

        return $pruned;
    }

    private function pruneOrphanedWorkspaces(string $maintenanceToken): int
    {
        $root = config()->string('assistant.workspaces', storage_path('app/ai-workspaces'));

        if (! $this->files->isDirectory($root)) {
            return 0;
        }

        $pruned = 0;

        foreach ($this->files->directories($root) as $directory) {
            if (! is_string($directory)) {
                continue;
            }

            $postId = basename($directory);

            if (! Str::isMatch('/\A\d+\z/', $postId)) {
                continue;
            }

            $numericPostId = (int) $postId;

            if (! $this->workspaceLease->acquire($numericPostId, $maintenanceToken)) {
                continue;
            }

            try {
                $session = AssistantSession::query()
                    ->select(['id', 'active_run_token'])
                    ->where('post_id', $numericPostId)
                    ->first();

                if ($session !== null) {
                    $runs = $directory.'/runs';

                    if (! $this->files->isDirectory($runs)) {
                        continue;
                    }

                    foreach ($this->files->directories($runs) as $runDirectory) {
                        if (! is_string($runDirectory)) {
                            continue;
                        }

                        $runToken = basename($runDirectory);
                        if (! Str::isUuid($runToken)) {
                            continue;
                        }
                        if ($runToken === $session->active_run_token) {
                            continue;
                        }

                        if ($this->files->deleteDirectory($runDirectory)) {
                            $this->prunedWorkspaceRoots[] = $runDirectory;
                            $pruned++;
                        }
                    }

                    continue;
                }

                if ($this->files->deleteDirectory($directory)) {
                    $this->prunedWorkspaceRoots[] = $directory;
                    $pruned++;
                }
            } finally {
                $this->workspaceLease->release($numericPostId, $maintenanceToken);
            }
        }

        return $pruned;
    }

    private function postReference(int $postId): Post
    {
        $post = new Post;
        $post->setAttribute($post->getKeyName(), $postId);

        return $post;
    }
}

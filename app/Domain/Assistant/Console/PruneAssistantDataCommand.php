<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Console;

use App\Domain\Assistant\Actions\PruneAssistantData;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Services\ClaudeTranscriptPruner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Prune old assistant turns and orphaned AI workspaces')]
#[Signature('assistant:prune')]
class PruneAssistantDataCommand extends Command
{
    public function handle(
        PruneAssistantData $prune,
        ClaudeTranscriptPruner $transcripts,
    ): int {
        $pruned = $prune->handle();
        $deletedTranscripts = $transcripts->deleteSessions($prune->prunedClaudeSessionIds());
        $retainedSessionIds = [];

        foreach (AssistantSession::query()->pluck('claude_session_id') as $sessionId) {
            if (is_string($sessionId)) {
                $retainedSessionIds[] = $sessionId;
            }
        }

        $deletedTranscripts += $transcripts->deleteExpiredExcept(
            $retainedSessionIds,
            now()->subDays(config()->integer('assistant.prune_after_days', 30)),
        );

        $this->info("Pruned {$pruned} assistant records and workspaces, plus {$deletedTranscripts} Claude transcripts.");

        return self::SUCCESS;
    }
}

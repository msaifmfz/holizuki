<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Reclaims disk and rows the assistant no longer needs: turns older than the
 * retention window and workspace directories for posts with no recent
 * assistant activity. Claude session files are recreatable, so pruning only
 * ever costs chat memory, never content.
 */
class PruneAssistantData
{
    public function __construct(private readonly Filesystem $files) {}

    public function handle(): int
    {
        $cutoff = now()->subDays(config()->integer('assistant.prune_after_days', 30));
        $pruned = 0;

        $deletedTurns = AssistantTurn::query()->where('created_at', '<', $cutoff)->delete();
        $pruned += is_int($deletedTurns) ? $deletedTurns : 0;

        $staleSessions = AssistantSession::query()
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('turns')
            ->get();

        foreach ($staleSessions as $session) {
            $session->delete();
            $pruned++;
        }

        $root = config()->string('assistant.workspaces', storage_path('app/ai-workspaces'));

        if ($this->files->isDirectory($root)) {
            // pgsql/mysql return bigint columns as strings under emulated
            // prepares, so normalize before the strict in_array below —
            // otherwise every workspace looks orphaned and gets deleted.
            $activePostIds = AssistantSession::query()
                ->pluck('post_id')
                ->map(static fn (mixed $id): int => match (true) {
                    is_int($id) => $id,
                    is_string($id) && Str::isMatch('/\A\d+\z/', $id) => (int) $id,
                    default => throw new UnexpectedValueException('Assistant session post IDs must be integers.'),
                })
                ->all();

            foreach ($this->files->directories($root) as $directory) {
                if (! is_string($directory)) {
                    continue;
                }

                $postId = basename($directory);

                if (Str::isMatch('/\A\d+\z/', $postId) && ! in_array((int) $postId, $activePostIds, true)) {
                    $this->files->deleteDirectory($directory);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }
}

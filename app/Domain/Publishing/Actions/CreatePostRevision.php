<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRevision;
use App\Domain\Publishing\ValueObjects\PostRevisionSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreatePostRevision
{
    public function handle(
        Post $post,
        ?User $editor,
        PostRevisionEvent $event,
        ?PostRevisionSnapshot $snapshot = null,
    ): PostRevision {
        $latestRevisionNumber = $post->revisions()->max('revision_number');

        if (! is_int($latestRevisionNumber)) {
            $latestRevisionNumber = 0;
        }

        $snapshot ??= $post->draft()->firstOrFail()->snapshot();

        $revision = $post->revisions()->create([
            'editor_id' => $editor?->id,
            'revision_number' => $latestRevisionNumber + 1,
            'event' => $event,
            'snapshot' => $snapshot,
        ]);

        $this->prune($post, $revision->revision_number);

        return $revision;
    }

    private function prune(Post $post, int $latestRevisionNumber): void
    {
        $configuredLimit = config('blog.revision_limit', 50);
        $limit = max(1, is_int($configuredLimit) ? $configuredLimit : 50);
        $oldRevisions = $post->revisions()
            ->where('revision_number', '<=', $latestRevisionNumber - $limit)
            ->when(
                $post->scheduled_revision_id !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($post->scheduled_revision_id),
            )
            ->get(['id', 'referenced_paths']);

        if ($oldRevisions->isEmpty()) {
            return;
        }

        $paths = $oldRevisions
            ->flatMap(fn (PostRevision $revision): array => $revision->referenced_paths)
            ->unique();
        $post->revisions()->whereKey($oldRevisions->modelKeys())->delete();

        $draft = $post->draft()->firstOrFail();
        $livePaths = array_filter([
            $post->featured_image_path,
            $post->og_image_path,
            $draft->featured_image_path,
            $draft->og_image_path,
        ], static fn (?string $path): bool => $path !== null);

        $deletablePaths = $paths
            ->reject(fn (string $path): bool => in_array($path, $livePaths, true)
                || $post->revisions()->referencingPath($path)->exists())
            ->values()
            ->all();

        if ($deletablePaths === []) {
            return;
        }

        // Deleting files is not transactional; defer until the surrounding
        // transaction commits so a rollback cannot orphan referenced images.
        DB::afterCommit(fn () => Storage::disk('public')->delete($deletablePaths));
    }
}

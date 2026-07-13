<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreatePostRevision
{
    public function handle(Post $post, ?User $editor, PostRevisionEvent $event): PostRevision
    {
        $latestRevisionNumber = $post->revisions()->max('revision_number');

        if (! is_int($latestRevisionNumber)) {
            $latestRevisionNumber = 0;
        }

        $revision = $post->revisions()->create([
            'editor_id' => $editor?->id,
            'revision_number' => $latestRevisionNumber + 1,
            'event' => $event,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'featured_image_path' => $post->featured_image_path,
            'featured_image_alt' => $post->featured_image_alt,
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
            ->get(['id', 'featured_image_path']);

        if ($oldRevisions->isEmpty()) {
            return;
        }

        $paths = $oldRevisions->map(fn (PostRevision $revision): ?string => $revision->featured_image_path)->filter()->unique();
        $post->revisions()->whereKey($oldRevisions->modelKeys())->delete();

        $deletablePaths = $paths
            ->reject(fn (string $path): bool => $path === $post->featured_image_path
                || $post->revisions()->where('featured_image_path', $path)->exists())
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

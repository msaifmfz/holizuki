<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Support\PublicCache;
use Illuminate\Support\Facades\DB;

class RestorePostRevision
{
    use ResolvesLockedPost;

    public function __construct(
        private readonly CreatePostRevision $createRevision,
        private readonly RebuildPostMetadata $rebuildPostMetadata,
        private readonly RecordSlugChange $recordSlugChange,
    ) {}

    public function handle(Post $post, PostRevision $revision, User $editor, int $expectedVersion): Post
    {
        $restored = DB::transaction(function () use ($post, $revision, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            $previousSlug = $current->slug;
            $current->fill([
                'title' => $revision->title,
                'slug' => $revision->slug,
                'excerpt' => $revision->excerpt,
                'body' => $revision->body,
                'featured_image_path' => $revision->featured_image_path,
                'featured_image_alt' => $revision->featured_image_alt,
                'featured_image_caption' => $revision->featured_image_caption,
                'seo_title' => $revision->seo_title,
                'meta_description' => $revision->meta_description,
                'canonical_url' => $revision->canonical_url,
                'og_title' => $revision->og_title,
                'og_description' => $revision->og_description,
                'og_image_path' => $revision->og_image_path,
                'noindex' => $revision->noindex,
            ]);

            if ($current->isDirty(Post::CONTENT_FIELDS)) {
                $current->content_updated_at = now();
            }

            $current->slug_is_manual = true;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->recordSlugChange->handle($current, $previousSlug);

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Restored);
            $this->rebuildPostMetadata->handle($current);

            return $current->refresh();
        });

        if ($restored->status === PostStatus::Published) {
            PublicCache::flush();
        }

        return $restored;
    }
}

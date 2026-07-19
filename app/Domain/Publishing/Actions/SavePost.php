<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Events\PostContentUpdated;
use App\Domain\Publishing\Exceptions\PostEditConflictException;
use App\Domain\Publishing\Models\Post;
use App\Support\Concerns\ResolvesUniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SavePost
{
    use ResolvesLockedPost, ResolvesUniqueSlug;

    /** @var list<string> */
    private const array NULLABLE_STRING_FIELDS = [
        'title', 'excerpt', 'featured_image_alt', 'featured_image_caption',
        'seo_title', 'meta_description', 'canonical_url', 'og_title', 'og_description',
    ];

    public function __construct(
        private readonly CreatePostRevision $createRevision,
        private readonly SyncPostTags $syncTags,
        private readonly RebuildPostMetadata $rebuildPostMetadata,
        private readonly RecordSlugChange $recordSlugChange,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Post $post,
        array $data,
        User $editor,
        bool $createRevision = false,
        bool $force = false,
    ): Post {
        $saved = DB::transaction(function () use ($post, $data, $editor, $createRevision, $force): Post {
            $current = $this->lockedPost($post);
            $expectedVersion = $data['lock_version'] ?? null;

            if (! is_numeric($expectedVersion)) {
                throw new InvalidArgumentException('The lock version must be an integer.');
            }

            if ($current->lock_version !== (int) $expectedVersion) {
                if (! $force) {
                    throw new PostEditConflictException($current->load('lastEditor:id,name'));
                }

                $this->createRevision->handle($current, $editor, PostRevisionEvent::ConflictOverwrite);
            }

            $previousSlug = $current->slug;
            $current->fill($this->attributes($data, $current));
            $this->applySlug($current, $data);

            if ($current->isDirty(Post::CONTENT_FIELDS)) {
                $current->content_updated_at = now();
            }

            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->recordSlugChange->handle($current, $previousSlug);

            if (array_key_exists('tags', $data)) {
                $this->syncTags->handle($current, is_array($data['tags']) ? $data['tags'] : []);
            }

            $this->rebuildPostMetadata->handle($current);

            if ($createRevision) {
                $this->createRevision->handle($current, $editor, PostRevisionEvent::Saved);
            }

            return $current->refresh()->load('author:id,name', 'lastEditor:id,name');
        });

        if ($saved->status === PostStatus::Published) {
            event(new PostContentUpdated($saved));
        }

        return $saved;
    }

    /**
     * Build the fillable attributes from the payload. Omitted keys leave the
     * stored value untouched, so partial payloads cannot wipe content.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Post $current): array
    {
        $attributes = [];

        foreach (self::NULLABLE_STRING_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $this->nullableString($data[$field]);
            }
        }

        if (array_key_exists('body', $data)) {
            $attributes['body'] = is_array($data['body']) ? $data['body'] : null;
        }

        if (array_key_exists('category_id', $data)) {
            $attributes['category_id'] = is_numeric($data['category_id']) ? (int) $data['category_id'] : null;
        }

        if (array_key_exists('author_id', $data)) {
            $attributes['author_id'] = is_numeric($data['author_id']) ? (int) $data['author_id'] : null;
        }

        $slugIsManual = $data['slug_is_manual'] ?? $current->slug_is_manual;

        if (! is_bool($slugIsManual)) {
            throw new InvalidArgumentException('The manual slug flag must be a boolean.');
        }

        $noindex = $data['noindex'] ?? $current->noindex;

        if (! is_bool($noindex)) {
            throw new InvalidArgumentException('The noindex flag must be a boolean.');
        }

        $attributes['slug_is_manual'] = $slugIsManual;
        $attributes['noindex'] = $noindex;

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySlug(Post $current, array $data): void
    {
        if (! $current->slug_is_manual && $current->slug_locked_at === null && $current->title !== null) {
            $current->slug = $this->uniqueSlug($current->title, $current->id);

            return;
        }

        $slug = $data['slug'] ?? $current->slug;

        if (! is_string($slug)) {
            return;
        }

        $normalized = Str::slug($slug);
        $current->slug = $normalized === ''
            ? $this->uniqueSlug((string) $current->title, $current->id)
            : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function uniqueSlug(string $title, int $postId): string
    {
        return $this->resolveUniqueSlug($title, Post::class, $postId, fallback: 'untitled-post');
    }
}

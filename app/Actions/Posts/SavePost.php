<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Exceptions\PostEditConflictException;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SavePost
{
    public function __construct(
        private readonly CreatePostRevision $createRevision,
        private readonly SyncPostTags $syncTags,
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
        return DB::transaction(function () use ($post, $data, $editor, $createRevision, $force): Post {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $expectedVersion = $data['lock_version'] ?? null;

            if (! is_numeric($expectedVersion)) {
                throw new InvalidArgumentException('The lock version must be an integer.');
            }

            $expectedVersion = (int) $expectedVersion;

            if ($current->lock_version !== $expectedVersion) {
                if (! $force) {
                    throw new PostEditConflictException($current->load('lastEditor:id,name'));
                }

                $this->createRevision->handle($current, $editor, PostRevisionEvent::ConflictOverwrite);
            }

            $attributes = [
                'title' => $data['title'] ?? null,
                'slug' => $data['slug'] ?? $current->slug,
                'slug_is_manual' => $data['slug_is_manual'] ?? $current->slug_is_manual,
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'] ?? null,
                'featured_image_alt' => $data['featured_image_alt'] ?? null,
            ];

            $attributes['title'] = $this->nullableString($attributes['title'] ?? null);
            $attributes['excerpt'] = $this->nullableString($attributes['excerpt'] ?? null);
            $attributes['featured_image_alt'] = $this->nullableString($attributes['featured_image_alt'] ?? null);

            if (array_key_exists('category_id', $data)) {
                $attributes['category_id'] = is_numeric($data['category_id']) ? (int) $data['category_id'] : null;
            }

            if (array_key_exists('author_id', $data)) {
                $attributes['author_id'] = is_numeric($data['author_id']) ? (int) $data['author_id'] : null;
            }

            $slugIsManual = $attributes['slug_is_manual'];

            if (! is_bool($slugIsManual)) {
                throw new InvalidArgumentException('The manual slug flag must be a boolean.');
            }

            if (! $slugIsManual && $current->slug_locked_at === null && $attributes['title'] !== null) {
                $attributes['slug'] = $this->uniqueSlug($attributes['title'], $current->id);
            } elseif (is_string($attributes['slug'])) {
                $slug = Str::slug($attributes['slug']);
                $attributes['slug'] = $slug === ''
                    ? $this->uniqueSlug(is_string($attributes['title']) ? $attributes['title'] : '', $current->id)
                    : $slug;
            }

            $current->fill($attributes);
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            if (array_key_exists('tags', $data)) {
                $this->syncTags->handle($current, is_array($data['tags']) ? $data['tags'] : []);
            }

            if ($createRevision) {
                $this->createRevision->handle($current, $editor, PostRevisionEvent::Saved);
            }

            return $current->refresh()->load('author:id,name', 'lastEditor:id,name');
        });
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
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'untitled-post';
        }
        $slug = $base;
        $suffix = 2;

        while (Post::withTrashed()->where('slug', $slug)->whereKeyNot($postId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}

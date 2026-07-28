<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Exceptions\PostEditConflictException;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostDraft;
use App\Support\ValueObjects\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class SavePostDraft
{
    use ResolvesLockedPost;

    /** @var list<string> */
    private const array NULLABLE_STRING_FIELDS = [
        'title', 'excerpt', 'featured_image_alt', 'featured_image_caption',
        'seo_title', 'meta_description', 'canonical_url', 'og_title', 'og_description',
    ];

    public function __construct(
        private CreatePostRevision $createRevision,
        private SyncPostTags $syncTags,
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
            $current = $this->lockedPost($post);
            $draft = $current->draft()->firstOrFail();
            $expectedVersion = $data['lock_version'] ?? null;

            if (! is_numeric($expectedVersion)) {
                throw new InvalidArgumentException('The lock version must be an integer.');
            }

            if ($current->lock_version !== (int) $expectedVersion) {
                if (! $force) {
                    throw new PostEditConflictException($current->load('lastEditor:id,name'));
                }

                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::ConflictOverwrite,
                    $draft->snapshot(),
                );
            }

            $draft->fill($this->attributes($data, $draft));
            $this->applySlug($current, $draft, $data);
            $draft->save();

            if (array_key_exists('tags', $data)) {
                $this->syncTags->handle($draft, is_array($data['tags']) ? $data['tags'] : []);
            }

            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            if ($createRevision) {
                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::Saved,
                    $draft->refresh()->snapshot(),
                );
            }

            return $current->refresh()->load([
                'author:id,name',
                'lastEditor:id,name',
                'draft.author:id,name',
                'draft.tags:id,name',
            ]);
        });
    }

    /**
     * Omitted keys retain their current values, so partial autosaves cannot
     * clear unrelated draft content.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, PostDraft $draft): array
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

        $slugIsManual = $data['slug_is_manual'] ?? $draft->slug_is_manual;

        if (! is_bool($slugIsManual)) {
            throw new InvalidArgumentException('The manual slug flag must be a boolean.');
        }

        $noindex = $data['noindex'] ?? $draft->noindex;

        if (! is_bool($noindex)) {
            throw new InvalidArgumentException('The noindex flag must be a boolean.');
        }

        $attributes['slug_is_manual'] = $slugIsManual;
        $attributes['noindex'] = $noindex;

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function applySlug(Post $post, PostDraft $draft, array $data): void
    {
        if (! $draft->slug_is_manual && $post->slug_locked_at === null && $draft->title !== null) {
            $draft->slug = $this->uniqueSlug($draft->title, $post, $draft);

            return;
        }

        $slug = $data['slug'] ?? $draft->slug;

        if (! is_string($slug)) {
            return;
        }

        $normalized = Str::slug($slug);
        $draft->slug = $this->uniqueSlug(
            $normalized === '' ? (string) $draft->title : $normalized,
            $post,
            $draft,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function uniqueSlug(string $value, Post $post, PostDraft $draft): string
    {
        $base = Slug::fromName($value, 'untitled-post');
        $candidate = $base;
        $suffix = 2;

        while (
            Post::withTrashed()->where('slug', $candidate->value)->whereKeyNot($post->id)->exists()
            || PostDraft::query()->where('slug', $candidate->value)->whereKeyNot($draft->id)->exists()
        ) {
            $candidate = $base->withSuffix($suffix++);
        }

        return $candidate->value;
    }
}

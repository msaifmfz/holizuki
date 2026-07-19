<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesUniqueSlug;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class SyncPostTags
{
    use ResolvesUniqueSlug;

    private const int MAX_TAGS = 10;

    /**
     * Sync the post's tags from a list of names, creating missing tags.
     *
     * @param  array<mixed>  $names
     */
    public function handle(Post $post, array $names): void
    {
        $normalized = [];

        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }

            $name = trim($name);
            $key = Str::lower($name);
            if ($name === '') {
                continue;
            }
            if (isset($normalized[$key])) {
                continue;
            }

            $normalized[$key] = $name;
        }

        $ids = [];

        foreach (array_slice($normalized, 0, self::MAX_TAGS) as $key => $name) {
            $ids[] = $this->resolveTag($key, $name)->id;
        }

        $post->tags()->sync($ids);
    }

    /**
     * Find the tag by its lowercased name, creating it when missing. A
     * concurrent request can win the create; on a unique violation fall back
     * to the row that won.
     */
    private function resolveTag(string $key, string $name): Tag
    {
        $tag = Tag::query()->whereRaw('lower(name) = ?', [$key])->first();

        if ($tag !== null) {
            return $tag;
        }

        try {
            return Tag::create([
                'name' => $name,
                'slug' => $this->resolveUniqueSlug($name, Tag::class),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            return Tag::query()->whereRaw('lower(name) = ?', [$key])->first() ?? throw $exception;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesUniqueSlug;
use App\Models\Post;
use App\Models\Tag;
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
            $tag = Tag::query()->whereRaw('lower(name) = ?', [$key])->first();
            $tag ??= Tag::create([
                'name' => $name,
                'slug' => $this->resolveUniqueSlug($name, Tag::class),
            ]);

            $ids[] = $tag->id;
        }

        $post->tags()->sync($ids);
    }
}

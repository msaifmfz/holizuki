<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RebuildPostMetadata
{
    public function handle(Post $post): Post
    {
        $post->loadMissing(['category:id,name', 'author:id,name', 'tags:id,name']);

        $searchText = Str::of(implode(' ', [
            ...array_fill(0, 4, (string) $post->title),
            ...array_fill(0, 3, (string) $post->excerpt),
            ...array_fill(0, 2, (string) $post->category?->name),
            ...array_fill(0, 2, (string) $post->author?->name),
            ...$post->tags->flatMap(
                fn (Tag $tag): array => array_fill(0, 2, $tag->name),
            )->all(),
            $post->body?->plainText() ?? '',
        ]))->squish()->toString();

        $post->forceFill([
            'reading_time_minutes' => $post->body?->readingTime() ?? 1,
            'search_text' => $searchText,
        ]);

        if ($post->isDirty(['reading_time_minutes', 'search_text'])) {
            Post::withoutTimestamps(fn (): bool => $post->saveQuietly());
        }

        return $post;
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function handleQuery(Builder $query): int
    {
        $rebuilt = 0;

        $query
            ->with(['category:id,name', 'author:id,name', 'tags:id,name'])
            ->lazyById()
            ->each(function (Post $post) use (&$rebuilt): void {
                $this->handle($post);
                $rebuilt++;
            });

        return $rebuilt;
    }
}

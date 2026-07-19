<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\Tag;
use App\Support\RichTextDocument;
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
            RichTextDocument::plainText($post->body),
        ]))->squish()->toString();

        $post->forceFill([
            'reading_time_minutes' => RichTextDocument::readingTime($post->body),
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

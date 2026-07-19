<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\AnalyticsUrlAlias;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRedirect;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProjectAnalyticsUrlAliases
{
    public function handle(Post $post): void
    {
        $post->loadMissing('author:id');
        $canonicalPath = '/posts/'.$post->slug;
        $contentKey = 'post:'.$post->id;
        $retiredAt = $post->isPublished() && $post->deleted_at === null ? null : now();

        DB::transaction(function () use ($post, $canonicalPath, $contentKey, $retiredAt): void {
            AnalyticsUrlAlias::query()
                ->where('content_key', $contentKey)
                ->where('path', '!=', $canonicalPath)
                ->update(['is_canonical' => false, 'retired_at' => now()]);

            $canonical = AnalyticsUrlAlias::query()->updateOrCreate(
                ['path' => $canonicalPath],
                [
                    'post_id' => $post->id,
                    'content_key' => $contentKey,
                    'is_canonical' => true,
                    'retired_at' => $retiredAt,
                ],
            );

            // Keep the canonical alias at least as fresh as the post so
            // reconcile() can cheaply skip posts that are already projected.
            if (! $canonical->wasRecentlyCreated && ! $canonical->wasChanged()) {
                $canonical->touch();
            }

            $redirects = PostRedirect::query()
                ->where('post_id', $post->id)
                ->get(['old_slug']);
            foreach ($redirects as $redirect) {
                AnalyticsUrlAlias::query()->updateOrCreate(
                    ['path' => '/posts/'.$redirect->old_slug],
                    [
                        'post_id' => $post->id,
                        'content_key' => $contentKey,
                        'is_canonical' => false,
                        'retired_at' => now(),
                    ],
                );
            }
        });
    }

    public function reconcile(): int
    {
        $count = 0;

        Post::withTrashed()
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('analytics_url_aliases')
                    ->whereColumn('analytics_url_aliases.post_id', 'posts.id')
                    ->where('analytics_url_aliases.is_canonical', true)
                    ->whereColumn('analytics_url_aliases.updated_at', '>=', 'posts.updated_at');
            })
            ->orderBy('id')
            ->chunkById(100, function (EloquentCollection $posts) use (&$count): void {
                foreach ($posts as $post) {
                    $this->handle($post);
                    $count++;
                }
            });

        return $count;
    }
}

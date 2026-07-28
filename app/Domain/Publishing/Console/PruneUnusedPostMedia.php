<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Console;

use App\Domain\Publishing\Enums\PostMediaOrigin;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('posts:prune-media')]
#[Description('Delete stale inline post images that are not referenced by a post or retained revision')]
class PruneUnusedPostMedia extends Command
{
    public function handle(): int
    {
        $deleted = 0;

        PostMedia::query()
            ->where('origin', PostMediaOrigin::InlineEditor)
            ->where('created_at', '<=', now()->subDay())
            ->chunkById(100, function (Collection $media) use (&$deleted): void {
                $posts = Post::withTrashed()
                    ->with([
                        'draft:id,post_id,body',
                        'revisions:id,post_id,referenced_media_ids',
                    ])
                    ->whereKey($media->pluck('post_id')->unique())
                    ->get(['id', 'body'])
                    ->keyBy('id');

                foreach ($media as $item) {
                    $post = $posts->get($item->post_id);

                    if ($post === null) {
                        continue;
                    }

                    $referencedIds = [
                        ...$post->body?->referencedMediaIds() ?? [],
                        ...$post->draft?->body?->referencedMediaIds() ?? [],
                    ];

                    foreach ($post->revisions as $revision) {
                        $referencedIds = [...$referencedIds, ...$revision->referenced_media_ids];
                    }

                    if (in_array($item->id, $referencedIds, true)) {
                        continue;
                    }

                    $disk = Storage::disk('public');

                    if (! $disk->delete($item->path) && $disk->exists($item->path)) {
                        continue;
                    }

                    $item->delete();
                    $deleted++;
                }
            });

        $this->components->info($deleted.' unused inline '.($deleted === 1 ? 'image' : 'images').' deleted.');

        return self::SUCCESS;
    }
}

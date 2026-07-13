<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Posts\PublishPost;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Description('Publish posts whose scheduled publication time has arrived')]
#[Signature('posts:publish-scheduled')]
class PublishScheduledPosts extends Command
{
    public function handle(PublishPost $publishPost): int
    {
        $published = 0;

        Post::query()
            ->where('status', PostStatus::Draft)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function (Collection $posts) use ($publishPost, &$published): void {
                foreach ($posts as $post) {
                    $publishPost->handle($post, editor: null, publishedAt: $post->scheduled_at);
                    $published++;
                }
            });

        $this->info("Published {$published} scheduled post(s).");

        return self::SUCCESS;
    }
}

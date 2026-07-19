<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Support\PublicCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SchedulePost
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, CarbonInterface $scheduledAt, int $expectedVersion): Post
    {
        $scheduled = DB::transaction(function () use ($post, $editor, $scheduledAt, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            $current->status = PostStatus::Draft;
            $current->scheduled_at = $scheduledAt;
            $current->featured_at = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Scheduled);

            return $current->refresh();
        });

        PublicCache::flush();

        return $scheduled;
    }
}

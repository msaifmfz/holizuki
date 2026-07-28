<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Events\PostScheduled;
use App\Domain\Publishing\Models\Post;
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
            $snapshot = $current->draft()->firstOrFail()->snapshot();
            $revision = $this->createRevision->handle(
                $current,
                $editor,
                PostRevisionEvent::Scheduled,
                $snapshot,
            );

            $current->schedule($scheduledAt, $revision, $editor);

            return $current->refresh()->load('scheduledRevision');
        });

        event(new PostScheduled($scheduled));

        return $scheduled;
    }
}

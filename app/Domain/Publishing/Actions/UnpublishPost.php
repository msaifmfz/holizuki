<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Events\PostUnpublished;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;

class UnpublishPost
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, int $expectedVersion): Post
    {
        $unpublished = DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            $current->unpublish($editor);

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Unpublished);

            return $current->refresh();
        });

        event(new PostUnpublished($unpublished));

        return $unpublished;
    }
}

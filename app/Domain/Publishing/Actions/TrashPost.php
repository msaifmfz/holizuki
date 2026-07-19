<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Events\PostTrashed;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;

class TrashPost
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor): void
    {
        DB::transaction(function () use ($post, $editor): void {
            $current = $this->lockedPost($post);
            $this->createRevision->handle($current, $editor, PostRevisionEvent::Deleted);

            $current->moveToTrash($editor);
        });

        event(new PostTrashed($post));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;

class DeleteComment
{
    public function handle(Comment $comment): void
    {
        $comment->forceFill([
            'status' => CommentStatus::Deleted,
            'deleted_at' => now(),
            'approved_at' => null,
        ])->save();
    }
}

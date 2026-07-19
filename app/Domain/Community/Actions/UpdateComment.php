<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use Illuminate\Support\Str;

class UpdateComment
{
    public function handle(Comment $comment, string $body): Comment
    {
        $normalizedBody = Str::of($body)->replace("\r\n", "\n")->replace("\r", "\n")->trim()->toString();
        $isAuthorReply = $comment->loadMissing('reader')->reader?->isAdministrator() === true;

        $comment->forceFill([
            'body' => e($normalizedBody),
            'body_hash' => hash('sha256', $normalizedBody),
            'status' => $isAuthorReply ? CommentStatus::Approved : CommentStatus::Pending,
            'moderated_by_id' => null,
            'moderation_reason' => null,
            'approved_at' => $isAuthorReply ? ($comment->approved_at ?? now()) : null,
            'rejected_at' => null,
        ])->save();

        return $comment->refresh();
    }
}

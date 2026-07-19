<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Events\CommentApproved;
use App\Domain\Community\Mail\CommentModeratedMail;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class ModerateComment
{
    public function handle(Comment $comment, User $moderator, CommentStatus $status, ?string $reason = null): Comment
    {
        if (! in_array($status, [CommentStatus::Approved, CommentStatus::Rejected, CommentStatus::Deleted], true)) {
            throw new InvalidArgumentException('Comments may only be approved, rejected, or deleted during moderation.');
        }

        $comment->forceFill([
            'status' => $status,
            'moderated_by_id' => $moderator->id,
            'moderation_reason' => $reason,
            'approved_at' => $status === CommentStatus::Approved ? now() : null,
            'rejected_at' => $status === CommentStatus::Rejected ? now() : null,
            'deleted_at' => $status === CommentStatus::Deleted ? now() : null,
        ])->save();

        $comment->loadMissing('reader:id,name,email');
        $reader = $comment->reader;

        if ($status !== CommentStatus::Deleted && $reader !== null) {
            Mail::to($reader->email)->send(new CommentModeratedMail($comment)->afterCommit());
        }

        if ($status === CommentStatus::Approved) {
            event(new CommentApproved($comment->id, $comment->post_id));
        }

        return $comment->refresh();
    }
}

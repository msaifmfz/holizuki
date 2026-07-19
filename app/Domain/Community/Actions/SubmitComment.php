<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Events\CommentSubmitted;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitComment
{
    public function handle(Post $post, User $author, string $body): Comment
    {
        $normalizedBody = Str::of($body)->replace("\r\n", "\n")->replace("\r", "\n")->trim()->toString();
        $bodyHash = hash('sha256', $normalizedBody);
        $isAuthorReply = $author->isAdministrator();

        $comment = DB::transaction(function () use ($post, $author, $normalizedBody, $bodyHash, $isAuthorReply): Comment {
            $isDuplicate = Comment::query()
                ->whereBelongsTo($post)
                ->where('user_id', $author->id)
                ->where('body_hash', $bodyHash)
                ->where('submitted_at', '>=', now()->subHours(config()->integer('community.comment_duplicate_hours')))
                ->lockForUpdate()
                ->exists();

            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'body' => 'You already submitted this comment recently.',
                ]);
            }

            $comment = Comment::query()->create([
                'post_id' => $post->id,
                'user_id' => $author->id,
                'body' => e($normalizedBody),
                'body_hash' => $bodyHash,
                'status' => $isAuthorReply ? CommentStatus::Approved : CommentStatus::Pending,
                'edit_deadline_at' => now()->addMinutes(config()->integer('community.comment_edit_minutes')),
                'submitted_at' => now(),
            ]);

            if ($isAuthorReply) {
                $comment->forceFill(['approved_at' => now()])->save();
            }

            return $comment;
        });

        if (! $isAuthorReply) {
            event(new CommentSubmitted($comment->id, $post->id));
        }

        return $comment;
    }
}

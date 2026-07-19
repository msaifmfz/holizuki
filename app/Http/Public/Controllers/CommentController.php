<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Community\Actions\DeleteComment;
use App\Domain\Community\Actions\SubmitComment;
use App\Domain\Community\Actions\UpdateComment;
use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Http\Controller;
use App\Http\Public\Requests\StoreCommentRequest;
use App\Http\Public\Requests\UpdateCommentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post, SubmitComment $submitComment): RedirectResponse
    {
        abort_unless($post->isPublished(), 404);
        $reader = $request->user();
        abort_unless($reader instanceof User, 403);

        $comment = $submitComment->handle($post, $reader, $request->string('body')->toString());

        return back()->with('comment_submitted', $comment->id);
    }

    public function update(UpdateCommentRequest $request, Comment $comment, UpdateComment $updateComment): RedirectResponse
    {
        $updated = $updateComment->handle($comment, $request->string('body')->toString());

        return back()->with('success', $updated->status === CommentStatus::Approved
            ? 'Your reply was updated.'
            : 'Your edit is pending moderation.');
    }

    public function destroy(Comment $comment, DeleteComment $deleteComment): RedirectResponse
    {
        Gate::authorize('delete', $comment);
        $deleteComment->handle($comment);

        return back()->with('success', 'Your comment was deleted.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Community\Actions\ModerateComment;
use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Http\Admin\Requests\ModerateCommentRequest;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommunityCommentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Comment::class);
        $status = $request->string('status')->toString();

        $comments = Comment::query()
            ->with(['post:id,title,slug', 'reader:id,name'])
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->latest('submitted_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Comment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->displayBody(),
                'status' => $comment->status->value,
                'reader_name' => $comment->reader->name ?? 'Deleted reader',
                'post' => [
                    'title' => $comment->post->title ?? 'Deleted post',
                    'slug' => $comment->post->slug ?? null,
                ],
                'submitted_at' => $comment->submitted_at->toISOString(),
                'reason' => $comment->moderation_reason,
            ]);

        return Inertia::render('community/comments/index', [
            'comments' => $comments,
            'filters' => ['status' => $status],
            'counts' => Comment::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function update(
        ModerateCommentRequest $request,
        Comment $comment,
        ModerateComment $moderateComment,
    ): RedirectResponse {
        $moderator = $request->user();
        abort_unless($moderator instanceof User, 403);
        $reason = $request->validated('reason');

        $moderateComment->handle(
            $comment,
            $moderator,
            CommentStatus::from($request->string('status')->toString()),
            is_string($reason) ? $reason : null,
        );

        return back()->with('success', 'Comment moderation updated.');
    }
}

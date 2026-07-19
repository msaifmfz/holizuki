<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Publishing\Actions\ForceDeletePost;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Queries\PostListing;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostTrashController extends Controller
{
    public function __construct(private readonly PostListing $postListing) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);
        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';
        $query = Post::onlyTrashed()
            ->select(['id', 'author_id', 'updated_by_id', 'title', 'slug', 'status', 'scheduled_at', 'published_at', 'featured_at', 'deleted_at', 'updated_at'])
            ->with(['author:id,name', 'lastEditor:id,name'])
            ->search($search)
            ->latest('deleted_at')
            ->orderByDesc('id');

        return Inertia::render('posts/index', [
            'posts' => $query->paginate(15)->withQueryString()->through(fn (Post $post): array => [
                ...$this->postListing->summary($post),
                'status' => 'trashed',
            ]),
            'filters' => ['search' => $search, 'status' => ''],
            'counts' => $this->postListing->statusCounts(),
            'trash' => true,
        ]);
    }

    public function restore(Post $post): RedirectResponse
    {
        Gate::authorize('restore', $post);
        $post->restore();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post restored as a draft.')]);

        return to_route('posts.edit', $post);
    }

    public function forceDestroy(Post $post, ForceDeletePost $forceDeletePost): RedirectResponse
    {
        Gate::authorize('forceDelete', $post);
        $forceDeletePost->handle($post);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post permanently deleted.')]);

        return to_route('posts.trash.index');
    }
}

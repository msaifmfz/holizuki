<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\BuildsPostListing;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostTrashController extends Controller
{
    use BuildsPostListing;

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
                ...$this->postSummary($post),
                'status' => 'trashed',
            ]),
            'filters' => ['search' => $search, 'status' => ''],
            'counts' => $this->postStatusCounts(),
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

    public function forceDestroy(Post $post): RedirectResponse
    {
        Gate::authorize('forceDelete', $post);
        $paths = $post->revisions()
            ->pluck('featured_image_path')
            ->merge($post->revisions()->pluck('og_image_path'))
            ->merge($post->media()->pluck('path'))
            ->push($post->featured_image_path, $post->og_image_path)
            ->filter()
            ->unique();
        $post->forceDelete();
        Storage::disk('public')->delete($paths->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post permanently deleted.')]);

        return to_route('posts.trash.index');
    }
}

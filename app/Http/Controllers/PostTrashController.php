<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostTrashController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);
        $search = $request->string('search')->trim()->toString();
        $query = Post::onlyTrashed()
            ->select(['id', 'author_id', 'title', 'slug', 'deleted_at', 'updated_at'])
            ->with('author:id,name')
            ->search($search)
            ->latest('deleted_at')
            ->orderByDesc('id');

        return Inertia::render('posts/index', [
            'posts' => $query->paginate(15)->withQueryString()->through(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title ?? 'Untitled post',
                'slug' => $post->slug,
                'status' => 'trashed',
                'author' => $post->author?->name,
                'last_editor' => null,
                'scheduled_at' => null,
                'published_at' => null,
                'updated_at' => $post->updated_at?->toISOString(),
            ]),
            'filters' => ['search' => $search, 'status' => ''],
            'counts' => ['all' => Post::count(), 'draft' => 0, 'scheduled' => 0, 'published' => 0, 'trash' => Post::onlyTrashed()->count()],
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
        $paths = $post->revisions()->pluck('featured_image_path')->push($post->featured_image_path)->filter()->unique();
        $post->forceDelete();
        Storage::disk('public')->delete($paths->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post permanently deleted.')]);

        return to_route('posts.trash.index');
    }
}

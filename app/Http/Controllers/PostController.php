<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\SavePost;
use App\Actions\Posts\TrashPost;
use App\Concerns\BuildsAutosaveResponse;
use App\Enums\PostStatus;
use App\Http\Requests\DeletePostRequest;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use BuildsAutosaveResponse;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);

        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();
        $query = Post::query()
            ->select(['id', 'author_id', 'updated_by_id', 'title', 'slug', 'status', 'scheduled_at', 'published_at', 'updated_at'])
            ->with(['author:id,name', 'lastEditor:id,name'])
            ->search($search)
            ->latest('updated_at')
            ->orderByDesc('id');

        match ($status) {
            'draft' => $query->where('status', PostStatus::Draft)->whereNull('scheduled_at'),
            'scheduled' => $query->scheduled(),
            'published' => $query->where('status', PostStatus::Published),
            default => null,
        };

        return Inertia::render('posts/index', [
            'posts' => $query->paginate(15)->withQueryString()->through(fn (Post $post): array => $this->summary($post)),
            'filters' => ['search' => $search, 'status' => $status],
            'counts' => [
                'all' => Post::count(),
                'draft' => Post::where('status', PostStatus::Draft)->whereNull('scheduled_at')->count(),
                'scheduled' => Post::scheduled()->count(),
                'published' => Post::where('status', PostStatus::Published)->count(),
                'trash' => Post::onlyTrashed()->count(),
            ],
            'trash' => false,
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $user = $request->authenticatedUser();
        $post = Post::create([
            'author_id' => $user->id,
            'updated_by_id' => $user->id,
            'title' => 'Untitled post',
            'slug' => 'untitled-post-'.Str::lower((string) Str::ulid()),
            'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        ]);

        return to_route('posts.edit', $post);
    }

    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        return Inertia::render('posts/edit', [
            'post' => $this->editorData($post->load('author:id,name', 'lastEditor:id,name', 'tags:id,name')),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'authors' => User::query()->orderBy('name')->get(['id', 'name']),
            'tagSuggestions' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post, SavePost $savePost): JsonResponse
    {
        $updatedPost = $savePost->handle(
            $post,
            $request->validated(),
            $request->authenticatedUser(),
            createRevision: true,
            force: $request->boolean('force'),
        );

        return response()->json($this->autosavePayload($updatedPost));
    }

    public function destroy(DeletePostRequest $request, Post $post, TrashPost $trashPost): RedirectResponse
    {
        $trashPost->handle($post, $request->authenticatedUser());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post moved to Trash.')]);

        return to_route('posts.index');
    }

    /** @return array<string, mixed> */
    private function summary(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title ?? 'Untitled post',
            'slug' => $post->slug,
            'status' => $post->isScheduled() ? 'scheduled' : $post->status->value,
            'author' => $post->author?->name,
            'last_editor' => $post->lastEditor?->name,
            'scheduled_at' => $post->scheduled_at?->toISOString(),
            'published_at' => $post->published_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function editorData(Post $post): array
    {
        return [
            ...$this->summary($post),
            'category_id' => $post->category_id,
            'author_id' => $post->author_id,
            'tags' => $post->tags->pluck('name')->all(),
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'featured_image_url' => $post->featured_image_path === null ? null : Storage::disk('public')->url($post->featured_image_path),
            'featured_image_alt' => $post->featured_image_alt,
            'slug_is_manual' => $post->slug_is_manual,
            'slug_locked_at' => $post->slug_locked_at?->toISOString(),
            'lock_version' => $post->lock_version,
            'created_at' => $post->created_at?->toISOString(),
        ];
    }
}

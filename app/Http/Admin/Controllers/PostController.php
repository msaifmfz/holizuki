<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Actions\SavePost;
use App\Domain\Publishing\Actions\TrashPost;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Queries\PostListing;
use App\Domain\Reading\Support\Seo;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use App\Http\Admin\Concerns\BuildsAutosaveResponse;
use App\Http\Admin\Requests\DeletePostRequest;
use App\Http\Admin\Requests\StorePostRequest;
use App\Http\Admin\Requests\UpdatePostRequest;
use App\Http\Controller;
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

    public function __construct(private readonly PostListing $postListing) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);

        $status = $request->query('status');
        $status = is_string($status) ? $status : '';
        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';
        $query = Post::query()
            ->select(['id', 'author_id', 'updated_by_id', 'title', 'slug', 'status', 'featured_at', 'scheduled_at', 'published_at', 'updated_at'])
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
            'posts' => $query->paginate(15)->withQueryString()->through(fn (Post $post): array => $this->postListing->summary($post)),
            'filters' => ['search' => $search, 'status' => $status],
            'counts' => $this->postListing->statusCounts(),
            'trash' => false,
        ]);
    }

    public function store(StorePostRequest $request, RebuildPostMetadata $rebuildPostMetadata): RedirectResponse
    {
        $user = $request->authenticatedUser();
        $post = Post::create([
            'author_id' => $user->id,
            'updated_by_id' => $user->id,
            'title' => 'Untitled post',
            'slug' => 'untitled-post-'.Str::lower((string) Str::ulid()),
            'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        ]);
        $rebuildPostMetadata->handle($post);

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
    private function editorData(Post $post): array
    {
        return [
            ...$this->postListing->summary($post),
            'category_id' => $post->category_id,
            'author_id' => $post->author_id,
            'tags' => $post->tags->pluck('name')->all(),
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'featured_image_url' => $post->featured_image_path === null ? null : Storage::disk('public')->url($post->featured_image_path),
            'featured_image_alt' => $post->featured_image_alt,
            'featured_image_caption' => $post->featured_image_caption,
            'reading_time_minutes' => $post->reading_time_minutes,
            'slug_is_manual' => $post->slug_is_manual,
            'slug_locked_at' => $post->slug_locked_at?->toISOString(),
            'lock_version' => $post->lock_version,
            'created_at' => $post->created_at?->toISOString(),
            'seo_title' => $post->seo_title,
            'meta_description' => $post->meta_description,
            'canonical_url' => $post->canonical_url,
            'og_title' => $post->og_title,
            'og_description' => $post->og_description,
            'og_image_url' => Seo::postOgImageUrl($post),
            'noindex' => $post->noindex,
        ];
    }
}

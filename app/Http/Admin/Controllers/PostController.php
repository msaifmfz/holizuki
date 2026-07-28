<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Analytics\Actions\TrackAuthorProductEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Actions\SavePostAuthoringJourney;
use App\Domain\Publishing\Actions\SavePostDraft;
use App\Domain\Publishing\Actions\TrashPost;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostDraft;
use App\Domain\Publishing\Queries\PostListing;
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
use Illuminate\Support\Facades\DB;
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
            ->select(['id', 'author_id', 'updated_by_id', 'title', 'slug', 'status', 'featured_at', 'scheduled_at', 'scheduled_revision_id', 'published_at', 'updated_at'])
            ->with([
                'author:id,name',
                'lastEditor:id,name',
                'draft:id,post_id,author_id,title,slug',
                'draft.author:id,name',
            ])
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

    public function store(
        StorePostRequest $request,
        RebuildPostMetadata $rebuildPostMetadata,
        SavePostAuthoringJourney $saveJourney,
        TrackAuthorProductEvent $trackEvent,
    ): RedirectResponse {
        $user = $request->authenticatedUser();
        $post = DB::transaction(function () use ($user, $rebuildPostMetadata, $saveJourney): Post {
            $post = Post::create([
                'author_id' => $user->id,
                'updated_by_id' => $user->id,
                'title' => 'Untitled post',
                'slug' => 'untitled-post-'.Str::lower((string) Str::ulid()),
                'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            ]);
            $rebuildPostMetadata->handle($post);
            $saveJourney->handle(
                $post,
                defaults: $user->authoringPreferences(),
            );

            return $post;
        });
        $trackEvent->handle($user, 'new_post_start', 'post:'.$post->id);

        return to_route('posts.edit', $post);
    }

    public function edit(Request $request, Post $post, TrackAuthorProductEvent $trackEvent): Response
    {
        Gate::authorize('update', $post);
        $user = $request->user();
        if ($user instanceof User) {
            $trackEvent->handle($user, 'editor_open', 'post:'.$post->id);
        }

        return Inertia::render('posts/edit', [
            'post' => $this->editorData($post->load([
                'author:id,name',
                'lastEditor:id,name',
                'draft.author:id,name',
                'draft.tags:id,name',
            ])),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'authors' => User::query()->orderBy('name')->get(['id', 'name']),
            'tagSuggestions' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(
        UpdatePostRequest $request,
        Post $post,
        SavePostDraft $savePost,
        TrackAuthorProductEvent $trackEvent,
    ): JsonResponse {
        $user = $request->authenticatedUser();
        $updatedPost = $savePost->handle(
            $post,
            $request->validated(),
            $user,
            createRevision: true,
            force: $request->boolean('force'),
        );
        $trackEvent->handle($user, 'editor_save', 'post:'.$post->id);

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
        $draft = $post->draft;

        if (! $draft instanceof PostDraft) {
            abort(409, 'This post does not have a working draft.');
        }

        return [
            ...$this->postListing->summary($post),
            'category_id' => $draft->category_id,
            'author_id' => $draft->author_id,
            'tags' => $draft->tags->pluck('name')->all(),
            'excerpt' => $draft->excerpt,
            'body' => $draft->body,
            'featured_image_url' => $draft->featured_image_path === null ? null : Storage::disk('public')->url($draft->featured_image_path),
            'featured_image_alt' => $draft->featured_image_alt,
            'featured_image_caption' => $draft->featured_image_caption,
            'reading_time_minutes' => $draft->body?->readingTime() ?? 1,
            'slug_is_manual' => $draft->slug_is_manual,
            'slug_locked_at' => $post->slug_locked_at?->toISOString(),
            'lock_version' => $post->lock_version,
            'created_at' => $post->created_at?->toISOString(),
            'seo_title' => $draft->seo_title,
            'meta_description' => $draft->meta_description,
            'canonical_url' => $draft->canonical_url,
            'og_title' => $draft->og_title,
            'og_description' => $draft->og_description,
            'og_image_url' => $draft->og_image_path === null ? null : Storage::disk('public')->url($draft->og_image_path),
            'noindex' => $draft->noindex,
        ];
    }
}

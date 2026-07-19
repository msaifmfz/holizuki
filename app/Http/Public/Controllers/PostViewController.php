<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRedirect;
use App\Domain\Reading\Actions\BuildReaderDocument;
use App\Domain\Reading\Queries\PublicPostCards;
use App\Domain\Reading\Support\Seo;
use App\Domain\Taxonomy\Models\Tag;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostViewController extends Controller
{
    public function __construct(private readonly PublicPostCards $postCards) {}

    public function show(string $slug, BuildReaderDocument $buildReaderDocument): Response|RedirectResponse
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'category:id,name,slug',
                'author:id,name,author_slug,avatar_path,bio,social_links',
                'tags:id,name,slug',
            ])
            ->first();

        if ($post === null) {
            return $this->redirectFromOldSlug($slug);
        }

        $reader = $buildReaderDocument->handle($post);
        $related = collect($this->relatedPosts($post))
            ->map(fn (Post $relatedPost): array => $this->postCards->card($relatedPost));
        $previous = $this->previousPost($post);
        $next = $this->nextPost($post);

        $imageUrl = $post->featured_image_path === null
            ? null
            : Storage::disk('public')->url($post->featured_image_path);

        return Inertia::render('public/posts/show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'seo_title' => $post->seo_title,
                'excerpt' => $post->excerpt,
                'body' => $reader['document'],
                'featured_image_url' => $imageUrl,
                'featured_image_alt' => $post->featured_image_alt,
                'featured_image_caption' => $post->featured_image_caption,
                'reading_time_minutes' => $post->reading_time_minutes ?? 1,
                'word_count' => $post->word_count,
                'word_count_band' => $post->word_count_band->value,
                'published_at' => $post->published_at?->toISOString(),
                'updated_at' => ($post->content_updated_at ?? $post->published_at)?->toISOString(),
                'category' => $post->category === null ? null : [
                    'name' => $post->category->name,
                    'slug' => $post->category->slug,
                ],
                'tags' => $post->tags->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])->all(),
                'author' => $post->author === null ? null : $this->postCards->authorProfile($post->author),
            ],
            'related' => $related,
            'previous' => $previous instanceof Post ? $this->postCards->card($previous) : null,
            'next' => $next instanceof Post ? $this->postCards->card($next) : null,
            'table_of_contents' => count($reader['table_of_contents']) >= 3 ? $reader['table_of_contents'] : [],
            'comments' => Inertia::scroll(fn () => Comment::query()
                ->whereBelongsTo($post)
                ->where('status', CommentStatus::Approved)
                ->with('reader:id,name,role')
                ->oldest('submitted_at')
                ->orderBy('id')
                ->paginate(perPage: 20, pageName: 'comments_page')
                ->through(fn (Comment $comment): array => $this->commentData($comment))),
            'myComments' => fn (): array => $this->privateComments($post),
            'seo' => Seo::forPost($post, $imageUrl, Seo::articleGraph($post, $imageUrl)),
        ]);
    }

    /**
     * Slug changes on published posts leave a post_redirects row behind;
     * resolve the old URL to the post's current slug with a permanent redirect.
     */
    private function redirectFromOldSlug(string $slug): RedirectResponse
    {
        $post = PostRedirect::query()->where('old_slug', $slug)->first()?->post;

        abort_if($post === null || $post->status !== PostStatus::Published, 404);

        return to_route('public.posts.show', $post->slug, 301);
    }

    /** @return list<Post> */
    private function relatedPosts(Post $post): array
    {
        $related = [];
        $excludedIds = [$post->id];
        $tagIds = $post->tags->modelKeys();

        if ($tagIds !== []) {
            $tagMatches = $this->postCards->query()
                ->whereKeyNot($excludedIds)
                ->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tagIds))
                ->withCount([
                    'tags as shared_tags_count' => fn (Builder $query): Builder => $query->whereKey($tagIds),
                ])
                ->reorder()
                ->orderByDesc('shared_tags_count')
                ->latest('published_at')
                ->orderByDesc('id')
                ->limit(3)
                ->get();
            foreach ($tagMatches as $tagMatch) {
                $related[] = $tagMatch;
            }
            $excludedIds = [...$excludedIds, ...$tagMatches->modelKeys()];
        }

        if (count($related) < 3 && $post->category_id !== null) {
            $categoryMatches = $this->postCards->query()
                ->whereKeyNot($excludedIds)
                ->where('category_id', $post->category_id)
                ->limit(3 - count($related))
                ->get();
            foreach ($categoryMatches as $categoryMatch) {
                $related[] = $categoryMatch;
            }
            $excludedIds = [...$excludedIds, ...$categoryMatches->modelKeys()];
        }

        if (count($related) < 3) {
            $recent = $this->postCards->query()
                ->whereKeyNot($excludedIds)
                ->limit(3 - count($related))
                ->get();
            foreach ($recent as $recentPost) {
                $related[] = $recentPost;
            }
        }

        return $related;
    }

    private function previousPost(Post $post): ?Post
    {
        return $this->postCards->query()
            ->where(function (Builder $query) use ($post): void {
                $query
                    ->where('published_at', '<', $post->published_at)
                    ->orWhere(function (Builder $sameTime) use ($post): void {
                        $sameTime->where('published_at', $post->published_at)->where('id', '<', $post->id);
                    });
            })
            ->first();
    }

    private function nextPost(Post $post): ?Post
    {
        return $this->postCards->query()
            ->where(function (Builder $query) use ($post): void {
                $query
                    ->where('published_at', '>', $post->published_at)
                    ->orWhere(function (Builder $sameTime) use ($post): void {
                        $sameTime->where('published_at', $post->published_at)->where('id', '>', $post->id);
                    });
            })
            ->reorder()
            ->oldest('published_at')
            ->orderBy('id')
            ->first();
    }

    /** @return array<string, mixed> */
    private function commentData(Comment $comment): array
    {
        $user = request()->user();

        return [
            'id' => $comment->id,
            'body' => $comment->displayBody(),
            'display_name' => $comment->reader->name ?? 'Reader',
            'is_author' => $comment->reader?->isAdministrator() === true,
            'submitted_at' => $comment->submitted_at->toISOString(),
            'can_edit' => $user instanceof User && $comment->isEditableBy($user),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function privateComments(Post $post): array
    {
        $user = request()->user();

        if (! $user instanceof User || ! $user->isReader()) {
            return [];
        }

        return array_values(Comment::query()
            ->whereBelongsTo($post)
            ->where('user_id', $user->id)
            ->whereIn('status', [CommentStatus::Pending, CommentStatus::Rejected])
            ->latest('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->displayBody(),
                'status' => $comment->status->value,
                'reason' => $comment->moderation_reason,
                'submitted_at' => $comment->submitted_at->toISOString(),
                'can_edit' => $comment->isEditableBy($user),
            ])
            ->values()
            ->all());
    }
}

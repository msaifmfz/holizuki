<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Actions\DeletePostMedia;
use App\Domain\Publishing\Actions\StoreFeaturedImage;
use App\Domain\Publishing\Actions\StorePostMediaFiles;
use App\Domain\Publishing\Actions\UpdatePostMedia;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use App\Http\Admin\Concerns\SerializesPostMedia;
use App\Http\Admin\Requests\DeletePostMediaRequest;
use App\Http\Admin\Requests\PostLockVersionRequest;
use App\Http\Admin\Requests\StorePostMediaRequest;
use App\Http\Admin\Requests\UpdatePostMediaRequest;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PostMediaController extends Controller
{
    use SerializesPostMedia;

    public function index(Post $post): JsonResponse
    {
        Gate::authorize('update', $post);
        $usedMediaIds = $post->draft()->firstOrFail()->body?->referencedMediaIds() ?? [];

        $media = PostMedia::query()
            ->whereBelongsTo($post)
            ->latest()
            ->get()
            ->map(fn (PostMedia $item): array => $this->serializePostMedia(
                $item,
                in_array($item->id, $usedMediaIds, true),
            ));

        return response()->json(['media' => $media]);
    }

    public function store(
        StorePostMediaRequest $request,
        Post $post,
        StorePostMediaFiles $storeMedia,
    ): JsonResponse {
        /** @var list<UploadedFile> $images */
        $images = $request->file('images', []);
        $media = $storeMedia
            ->handle($post, $images)
            ->map(fn (PostMedia $item): array => $this->serializePostMedia($item));

        return response()->json(['media' => $media], 201);
    }

    public function update(
        UpdatePostMediaRequest $request,
        Post $post,
        PostMedia $media,
        UpdatePostMedia $updateMedia,
    ): JsonResponse {
        $this->ensureMediaBelongsToPost($media, $post);
        /** @var array{display_name?: string, alt_text?: string|null, caption?: string|null, role?: string, placement?: string, story_priority?: string, story_notes?: string|null} $attributes */
        $attributes = $request->validated();
        $previousLockVersion = $post->lock_version;
        $updatedMedia = $updateMedia->handle($media, $attributes, $request->authenticatedUser());
        $post->refresh();
        $draft = $post->draft()->firstOrFail();
        $usedMediaIds = $draft->body?->referencedMediaIds() ?? [];
        // The action rewrites the draft body when a used image's alt text or
        // caption changes. Hand the rewritten body back with the new lock
        // version so the editor reconciles instead of re-sending stale content.
        $bodyWasRewritten = $post->lock_version !== $previousLockVersion;

        return response()->json([
            'media' => $this->serializePostMedia(
                $updatedMedia,
                in_array($updatedMedia->id, $usedMediaIds, true),
            ),
            'lock_version' => $post->lock_version,
            'body' => $bodyWasRewritten && $draft->body instanceof RichTextDocument
                ? $draft->body->toArray()
                : null,
        ]);
    }

    public function destroy(
        DeletePostMediaRequest $request,
        Post $post,
        PostMedia $media,
        DeletePostMedia $deleteMedia,
    ): JsonResponse {
        $this->ensureMediaBelongsToPost($media, $post);

        DB::transaction(function () use ($request, $post, $media, $deleteMedia): void {
            $currentPost = Post::query()
                ->withTrashed()
                ->whereKey($post->id)
                ->lockForUpdate()
                ->firstOrFail();
            $writtenCompositions = AuthoringImageComposition::query()
                ->whereBelongsTo($currentPost)
                ->whereNotNull('passage_markdown')
                ->where('passage_markdown', '!=', '')
                ->whereHas(
                    'media',
                    fn (Builder $query): Builder => $query->whereKey($media->id),
                )
                ->withCount('media')
                ->lockForUpdate()
                ->get();
            $orphanedWriting = $writtenCompositions->filter(function (AuthoringImageComposition $composition): bool {
                $mediaCount = $composition->getAttribute('media_count');

                return is_numeric($mediaCount) && (int) $mediaCount === 1;
            });

            if ($orphanedWriting->isNotEmpty() && ! $request->boolean('discard_story_writing')) {
                throw ValidationException::withMessages([
                    'media' => 'This photo has saved story writing. Confirm that you want to remove both the photo and that passage.',
                ]);
            }

            $orphanedWriting->each(function (AuthoringImageComposition $composition): void {
                $composition->deleteOrFail();
            });
            $deleteMedia->handle($currentPost, $media);
        });

        return response()->json(status: 204);
    }

    public function feature(
        PostLockVersionRequest $request,
        Post $post,
        PostMedia $media,
        StoreFeaturedImage $storeImage,
    ): JsonResponse {
        $this->ensureMediaBelongsToPost($media, $post);
        $updatedPost = $storeImage->useMedia(
            $post,
            $media,
            $request->authenticatedUser(),
            $request->integer('lock_version'),
        );
        $draft = $updatedPost->draft()->firstOrFail();

        return response()->json([
            'featured_image_url' => Storage::disk('public')->url((string) $draft->featured_image_path),
            'featured_image_alt' => $draft->featured_image_alt,
            'featured_image_caption' => $draft->featured_image_caption,
            'lock_version' => $updatedPost->lock_version,
            'updated_at' => $updatedPost->updated_at?->toISOString(),
            'media' => $this->serializePostMedia($media->refresh()),
        ]);
    }

    private function ensureMediaBelongsToPost(PostMedia $media, Post $post): void
    {
        abort_unless($media->post_id === $post->id, 404);
    }
}

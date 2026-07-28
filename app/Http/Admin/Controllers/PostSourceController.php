<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Publishing\Actions\CreatePostSourceNote;
use App\Domain\Publishing\Actions\CreatePostSourceUrl;
use App\Domain\Publishing\Actions\DeletePostSource;
use App\Domain\Publishing\Actions\StorePostSourceDocuments;
use App\Domain\Publishing\Actions\UpdatePostSource;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use App\Http\Admin\Concerns\SerializesPostSource;
use App\Http\Admin\Requests\StorePostSourceDocumentsRequest;
use App\Http\Admin\Requests\StorePostSourceNoteRequest;
use App\Http\Admin\Requests\StorePostSourceUrlRequest;
use App\Http\Admin\Requests\UpdatePostSourceRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PostSourceController extends Controller
{
    use SerializesPostSource;

    public function index(Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $sources = PostSource::query()
            ->whereBelongsTo($post)
            ->latest()
            ->get()
            ->map(fn (PostSource $source): array => $this->serializePostSource($source));

        return response()->json(['sources' => $sources]);
    }

    public function storeNote(
        StorePostSourceNoteRequest $request,
        Post $post,
        CreatePostSourceNote $createSource,
    ): JsonResponse {
        $source = $createSource->handle(
            $post,
            $request->authenticatedUser(),
            $request->filled('title') ? $request->string('title')->toString() : null,
            $request->string('content')->toString(),
        );

        return response()->json(['source' => $this->serializePostSource($source)], 201);
    }

    public function storeUrl(
        StorePostSourceUrlRequest $request,
        Post $post,
        CreatePostSourceUrl $createSource,
    ): JsonResponse {
        $source = $createSource->handle(
            $post,
            $request->authenticatedUser(),
            $request->filled('title') ? $request->string('title')->toString() : null,
            $request->string('url')->toString(),
        );

        return response()->json(['source' => $this->serializePostSource($source)], 201);
    }

    public function storeDocuments(
        StorePostSourceDocumentsRequest $request,
        Post $post,
        StorePostSourceDocuments $storeDocuments,
    ): JsonResponse {
        /** @var list<UploadedFile> $documents */
        $documents = $request->file('documents', []);
        $sources = $storeDocuments
            ->handle($post, $request->authenticatedUser(), $documents)
            ->map(fn (PostSource $source): array => $this->serializePostSource($source));

        return response()->json(['sources' => $sources], 201);
    }

    public function update(
        UpdatePostSourceRequest $request,
        Post $post,
        PostSource $source,
        UpdatePostSource $updateSource,
    ): JsonResponse {
        $this->ensureSourceBelongsToPost($source, $post);
        /** @var array{title?: string, approved?: bool, visibility?: string} $attributes */
        $attributes = $request->validated();
        $updatedSource = $updateSource->handle($source, $attributes);

        return response()->json(['source' => $this->serializePostSource($updatedSource)]);
    }

    public function download(Post $post, PostSource $source): StreamedResponse
    {
        Gate::authorize('update', $post);
        $this->ensureSourceBelongsToPost($source, $post);
        abort_if($source->path === null, 404);

        return Storage::disk('local')->download(
            $source->path,
            $source->original_name ?? basename($source->path),
        );
    }

    public function destroy(Post $post, PostSource $source, DeletePostSource $deleteSource): JsonResponse
    {
        Gate::authorize('update', $post);
        $this->ensureSourceBelongsToPost($source, $post);
        $deleteSource->handle($source);

        return response()->json(status: 204);
    }

    private function ensureSourceBelongsToPost(PostSource $source, Post $post): void
    {
        abort_unless($source->post_id === $post->id, 404);
    }
}

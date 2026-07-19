<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\RestorePostRevision;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRevision;
use App\Domain\Reading\Actions\BuildReaderDocument;
use App\Http\Admin\Requests\PostLockVersionRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostRevisionController extends Controller
{
    public function index(Post $post): Response
    {
        Gate::authorize('update', $post);

        return Inertia::render('posts/revisions/index', [
            'post' => ['id' => $post->id, 'title' => $post->title ?? 'Untitled post', 'lock_version' => $post->lock_version],
            'revisions' => $post->revisions()->with('editor:id,name')->latest('revision_number')->paginate(20)->through(
                fn (PostRevision $revision): array => $this->summary($revision),
            ),
        ]);
    }

    public function show(Post $post, PostRevision $revision, BuildReaderDocument $buildReaderDocument): Response
    {
        Gate::authorize('update', $post);
        $revisionPost = clone $post;
        $revisionPost->body = $revision->body;
        $reader = $buildReaderDocument->handle($revisionPost);

        return Inertia::render('posts/preview', [
            'post' => [
                'id' => $post->id,
                'title' => $revision->title ?? 'Untitled post',
                'excerpt' => $revision->excerpt,
                'body' => $reader['document'],
                'featured_image_url' => $revision->featured_image_path === null ? null : Storage::disk('public')->url($revision->featured_image_path),
                'featured_image_alt' => $revision->featured_image_alt,
                'featured_image_caption' => $revision->featured_image_caption,
                'reading_time_minutes' => $revision->body?->readingTime() ?? 1,
                'published_at' => null,
                'updated_at' => $revision->created_at->toISOString(),
            ],
            'revision' => $this->summary($revision->load('editor:id,name')),
        ]);
    }

    public function restore(PostLockVersionRequest $request, Post $post, PostRevision $revision, RestorePostRevision $restoreRevision): JsonResponse
    {
        $updatedPost = $restoreRevision->handle($post, $revision, $request->authenticatedUser(), $request->integer('lock_version'));

        return response()->json(['lock_version' => $updatedPost->lock_version]);
    }

    /** @return array<string, mixed> */
    private function summary(PostRevision $revision): array
    {
        $editor = $revision->editor;

        return [
            'id' => $revision->id,
            'revision_number' => $revision->revision_number,
            'event' => $revision->event->value,
            'editor' => $editor instanceof User ? $editor->name : 'System',
            'created_at' => $revision->created_at->toISOString(),
        ];
    }
}

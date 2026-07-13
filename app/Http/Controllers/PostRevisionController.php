<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\RestorePostRevision;
use App\Http\Requests\PostLockVersionRequest;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
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

    public function show(Post $post, PostRevision $revision): Response
    {
        Gate::authorize('update', $post);

        return Inertia::render('posts/preview', [
            'post' => [
                'id' => $post->id,
                'title' => $revision->title ?? 'Untitled post',
                'excerpt' => $revision->excerpt,
                'body' => $revision->body,
                'featured_image_url' => $revision->featured_image_path === null ? null : Storage::disk('public')->url($revision->featured_image_path),
                'featured_image_alt' => $revision->featured_image_alt,
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

<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Publishing\Actions\PublishPost;
use App\Domain\Publishing\Actions\SchedulePost;
use App\Domain\Publishing\Actions\UnpublishPost;
use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Actions\BuildReaderDocument;
use App\Http\Admin\Requests\PostLockVersionRequest;
use App\Http\Admin\Requests\PublishPostRequest;
use App\Http\Admin\Requests\SchedulePostRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostPublishingController extends Controller
{
    public function preview(Post $post, BuildReaderDocument $buildReaderDocument): Response
    {
        Gate::authorize('view', $post);

        return Inertia::render('posts/preview', [
            'post' => $this->previewData($post, $buildReaderDocument),
            'revision' => null,
        ]);
    }

    public function publish(PublishPostRequest $request, Post $post, PublishPost $publishPost): JsonResponse
    {
        $updatedPost = $publishPost->handle($post, $request->authenticatedUser(), $request->integer('lock_version'));

        return response()->json($this->state($updatedPost));
    }

    public function schedule(SchedulePostRequest $request, Post $post, SchedulePost $schedulePost): JsonResponse
    {
        $updatedPost = $schedulePost->handle(
            $post,
            $request->authenticatedUser(),
            Date::parse($request->string('scheduled_at')->toString()),
            $request->integer('lock_version'),
        );

        return response()->json($this->state($updatedPost));
    }

    public function unpublish(PostLockVersionRequest $request, Post $post, UnpublishPost $unpublishPost): JsonResponse
    {
        return response()->json($this->state(
            $unpublishPost->handle($post, $request->authenticatedUser(), $request->integer('lock_version')),
        ));
    }

    /** @return array<string, mixed> */
    private function state(Post $post): array
    {
        return [
            'status' => $post->isScheduled() ? 'scheduled' : $post->status->value,
            'lock_version' => $post->lock_version,
            'scheduled_at' => $post->scheduled_at?->toISOString(),
            'published_at' => $post->published_at?->toISOString(),
            'slug_locked_at' => $post->slug_locked_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function previewData(Post $post, BuildReaderDocument $buildReaderDocument): array
    {
        $reader = $buildReaderDocument->handle($post);

        return [
            'id' => $post->id,
            'title' => $post->title ?? 'Untitled post',
            'excerpt' => $post->excerpt,
            'body' => $reader['document'],
            'featured_image_url' => $post->featured_image_path === null ? null : Storage::disk('public')->url($post->featured_image_path),
            'featured_image_alt' => $post->featured_image_alt,
            'featured_image_caption' => $post->featured_image_caption,
            'reading_time_minutes' => $post->body?->readingTime() ?? 1,
            'published_at' => $post->published_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }
}

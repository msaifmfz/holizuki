<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\MarkChangeDecision;
use App\Domain\Assistant\Actions\ResolveChangePayload;
use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Exceptions\StaleChangeException;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Publishing\Actions\SavePost;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use App\Http\Admin\Concerns\BuildsAutosaveResponse;
use App\Http\Admin\Concerns\SerializesAssistantChanges;
use App\Http\Admin\Requests\Assistant\ChangeDecisionRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Applies or discards one AI proposal. Accepting re-resolves the change
 * against the post's current state and persists it through the same SavePost
 * pipeline (and lock_version discipline) as a manual save.
 */
class AssistantChangeController extends Controller
{
    use BuildsAutosaveResponse, SerializesAssistantChanges;

    public function __construct(
        private readonly ResolveChangePayload $resolvePayload,
        private readonly MarkChangeDecision $markDecision,
        private readonly SavePost $savePost,
    ) {}

    public function accept(ChangeDecisionRequest $request, Post $post, AssistantChange $change): JsonResponse
    {
        $this->ensureDecidable($post, $change);

        try {
            $payload = $this->resolvePayload->handle($change, $post);
        } catch (StaleChangeException $exception) {
            $this->markDecision->handle($change, AssistantChangeStatus::Stale);

            return response()->json(['message' => $exception->getMessage(), 'change' => $this->assistantChangeData($change)], 422);
        }

        $saved = $this->savePost->handle($post, $payload, $request->authenticatedUser());
        $this->markDecision->handle($change, AssistantChangeStatus::Accepted);

        return response()->json([
            'change' => $this->assistantChangeData($change),
            'post' => [
                ...$this->autosavePayload($saved),
                'title' => $saved->title,
                'excerpt' => $saved->excerpt,
                'seo_title' => $saved->seo_title,
                'meta_description' => $saved->meta_description,
                'og_title' => $saved->og_title,
                'og_description' => $saved->og_description,
                'featured_image_alt' => $saved->featured_image_alt,
                'featured_image_caption' => $saved->featured_image_caption,
                'tags' => $saved->tags()->pluck('name')->values()->all(),
                'body' => $saved->body instanceof RichTextDocument ? $saved->body->toArray() : null,
            ],
        ]);
    }

    public function reject(ChangeDecisionRequest $request, Post $post, AssistantChange $change): JsonResponse
    {
        $this->ensureDecidable($post, $change);
        $this->markDecision->handle($change, AssistantChangeStatus::Rejected);

        return response()->json(['change' => $this->assistantChangeData($change)]);
    }

    private function ensureDecidable(Post $post, AssistantChange $change): void
    {
        abort_unless($change->post_id === $post->id, 404);
        abort_unless($change->status === AssistantChangeStatus::Proposed, 409, 'This suggestion was already decided.');
    }
}

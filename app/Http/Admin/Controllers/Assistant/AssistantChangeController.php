<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\ResolveChangePayload;
use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Exceptions\StaleChangeException;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\SavePost;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use App\Http\Admin\Concerns\BuildsAutosaveResponse;
use App\Http\Admin\Concerns\SerializesAssistantChanges;
use App\Http\Admin\Requests\Assistant\ChangeDecisionRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use LogicException;

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
        private readonly SavePost $savePost,
    ) {}

    public function accept(ChangeDecisionRequest $request, Post $post, AssistantChange $change): JsonResponse
    {
        [$saved, $decidedChange, $stale] = $this->acceptAtomically(
            $post,
            $change,
            $request->authenticatedUser(),
        );

        if ($stale instanceof StaleChangeException) {
            return response()->json([
                'code' => 'stale_change',
                'message' => $stale->getMessage(),
                'change' => $this->assistantChangeData($decidedChange),
            ], 409);
        }

        if ($saved === null) {
            throw new LogicException('An accepted assistant change must return the saved post.');
        }

        return response()->json([
            'change' => $this->assistantChangeData($decidedChange),
            'post' => [
                ...$this->autosavePayload($saved),
                'title' => $saved->title,
                'slug_is_manual' => $saved->slug_is_manual,
                'excerpt' => $saved->excerpt,
                'category_id' => $saved->category_id,
                'author_id' => $saved->author_id,
                'seo_title' => $saved->seo_title,
                'meta_description' => $saved->meta_description,
                'canonical_url' => $saved->canonical_url,
                'og_title' => $saved->og_title,
                'og_description' => $saved->og_description,
                'noindex' => $saved->noindex,
                'featured_image_alt' => $saved->featured_image_alt,
                'featured_image_caption' => $saved->featured_image_caption,
                'tags' => $saved->tags()->pluck('name')->values()->all(),
                'body' => $saved->body instanceof RichTextDocument ? $saved->body->toArray() : null,
            ],
        ]);
    }

    public function reject(ChangeDecisionRequest $request, Post $post, AssistantChange $change): JsonResponse
    {
        $decidedChange = $this->rejectAtomically($post, $change);

        return response()->json(['change' => $this->assistantChangeData($decidedChange)]);
    }

    /**
     * @return array{Post|null, AssistantChange, StaleChangeException|null}
     */
    private function acceptAtomically(Post $post, AssistantChange $change, User $user): array
    {
        return DB::transaction(function () use ($post, $change, $user): array {
            [$lockedPost, $lockedChange] = $this->lockDecisionGraph(
                $post,
                $change,
                requireIdleSession: true,
            );

            try {
                $payload = $this->resolvePayload->handle($lockedChange, $lockedPost);
            } catch (StaleChangeException $exception) {
                $lockedChange->forceFill([
                    'status' => AssistantChangeStatus::Stale,
                    'decided_at' => now(),
                ])->save();

                return [null, $lockedChange, $exception];
            }

            $saved = $this->savePost->handle(
                $lockedPost,
                $payload,
                $user,
                createRevision: true,
            );

            $lockedChange->forceFill([
                'status' => AssistantChangeStatus::Accepted,
                'decided_at' => now(),
            ])->save();

            return [$saved, $lockedChange, null];
        });
    }

    private function rejectAtomically(Post $post, AssistantChange $change): AssistantChange
    {
        return DB::transaction(function () use ($post, $change): AssistantChange {
            [, $lockedChange] = $this->lockDecisionGraph($post, $change);

            $lockedChange->forceFill([
                'status' => AssistantChangeStatus::Rejected,
                'decided_at' => now(),
            ])->save();

            return $lockedChange;
        });
    }

    /** @return array{Post, AssistantChange} */
    private function lockDecisionGraph(
        Post $post,
        AssistantChange $change,
        bool $requireIdleSession = false,
    ): array {
        $sessionId = AssistantTurn::query()
            ->whereKey($change->assistant_turn_id)
            ->value('assistant_session_id');

        abort_if($sessionId === null, 404);

        $lockedSession = AssistantSession::query()
            ->whereKey($sessionId)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $requireIdleSession
            && $lockedSession->active_run_token !== null
            && $lockedSession->turns()
                ->where('run_token', $lockedSession->active_run_token)
                ->whereIn('status', [
                    AssistantTurnStatus::Running,
                    AssistantTurnStatus::Cancelling,
                ])
                ->exists()
        ) {
            abort(409, 'Wait for the active assistant turn to finish before accepting a suggestion.');
        }

        AssistantTurn::query()->whereKey($change->assistant_turn_id)->lockForUpdate()->firstOrFail();

        /** @var Post $lockedPost */
        $lockedPost = Post::query()->withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();

        /** @var AssistantChange $lockedChange */
        $lockedChange = AssistantChange::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();

        abort_unless($lockedChange->post_id === $lockedPost->id, 404);

        if ($lockedChange->status !== AssistantChangeStatus::Proposed) {
            abort(409, 'This suggestion was already decided.');
        }

        return [$lockedPost, $lockedChange];
    }
}

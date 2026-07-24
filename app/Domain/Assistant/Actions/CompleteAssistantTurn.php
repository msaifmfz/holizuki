<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\BlockDiffer;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;
use UnexpectedValueException;

/**
 * Closes a successful agent turn: diffs the workspace against the pre-turn
 * snapshot and records every difference as a reviewable proposal. A body
 * rewrite that fails the rich-text whitelist fails the whole turn — invalid
 * content can never become a proposal.
 */
class CompleteAssistantTurn
{
    public function __construct(
        private readonly WorkspaceManager $workspace,
        private readonly BlockDiffer $differ,
        private readonly MarkdownParser $parser,
        private readonly FailAssistantTurn $failTurn,
    ) {}

    /**
     * @param  array<string, mixed>|null  $usage
     * @return list<AssistantChange>
     */
    public function handle(
        AssistantTurn $turn,
        Post $post,
        ?string $assistantMessage,
        ?float $costUsd,
        ?int $durationMs,
        ?string $resultSubtype = 'success',
        ?string $stopReason = null,
        ?array $usage = null,
    ): array {
        try {
            $newDraft = $this->workspace->readDraft($post, $turn);
            $newMeta = $this->workspace->readMeta($post, $turn);
            $outline = $turn->task_type->usesPersistentSession()
                ? $this->workspace->readOutline($post, $turn)
                : null;
            $snapshotDraft = $turn->snapshot_draft ?? '';

            if (
                ($newDraft !== $snapshotDraft && ! $this->draftIsValid($post, $newDraft))
                || ! $this->metaIsValid($newMeta)
            ) {
                throw new UnexpectedValueException('The assistant output failed publishing validation.');
            }

            $hunks = $this->differ->diff($snapshotDraft, $newDraft);
            $fieldChanges = $this->fieldChanges($turn->snapshot_meta ?? [], $newMeta);
        } catch (Throwable $exception) {
            if (! $exception instanceof UnexpectedValueException) {
                report($exception);
            }

            $this->failTurn->handle(
                $turn,
                'The assistant produced invalid output. Nothing was changed.',
                errorCode: 'invalid_assistant_output',
            );

            return [];
        }

        return DB::transaction(function () use (
            $turn,
            $post,
            $assistantMessage,
            $costUsd,
            $durationMs,
            $resultSubtype,
            $stopReason,
            $usage,
            $hunks,
            $fieldChanges,
            $outline,
        ): array {
            /** @var AssistantSession $session */
            $session = AssistantSession::query()
                ->whereKey($turn->assistant_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var AssistantTurn $lockedTurn */
            $lockedTurn = AssistantTurn::query()->whereKey($turn->id)->lockForUpdate()->firstOrFail();

            if (
                $lockedTurn->status !== AssistantTurnStatus::Running
                || $session->status !== AssistantSessionStatus::Running
                || $session->active_run_token !== $lockedTurn->run_token
            ) {
                return [];
            }

            /** @var Post $lockedPost */
            $lockedPost = Post::query()->withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $changes = [];

            if ($hunks !== []) {
                $this->supersede($lockedPost, [AssistantChangeType::Body]);
            }

            foreach ($hunks as $hunk) {
                $changes[] = $this->createChange($lockedTurn, $lockedPost, AssistantChangeType::Body, $hunk);
            }

            $fieldTypes = [];

            foreach (array_keys($fieldChanges) as $key) {
                $type = AssistantChangeType::fromMetaKey($key);

                if ($type instanceof AssistantChangeType) {
                    $fieldTypes[$key] = $type;
                }
            }

            $this->supersede($lockedPost, array_values($fieldTypes));

            foreach ($fieldTypes as $key => $type) {
                $changes[] = $this->createChange($lockedTurn, $lockedPost, $type, $fieldChanges[$key]);
            }

            $lockedTurn->forceFill([
                'status' => AssistantTurnStatus::Completed,
                'assistant_message' => $assistantMessage,
                'result_subtype' => $resultSubtype,
                'stop_reason' => $stopReason,
                'usage' => $usage,
                'cost_usd' => $costUsd,
                'duration_ms' => $durationMs,
                'error_code' => null,
                'error_message' => null,
                'retry_at' => null,
                'finished_at' => now(),
            ])->save();

            $sessionUpdates = [
                'status' => AssistantSessionStatus::Idle,
                'active_run_token' => null,
                'turn_started_at' => null,
            ];

            if ($lockedTurn->task_type->usesPersistentSession()) {
                $sessionUpdates['resume_ready'] = true;
                $sessionUpdates['conversation_model'] = $lockedTurn->task_type->model();
                $this->applyOutlineResult($session, $lockedTurn, $outline, $sessionUpdates);
            }

            $session->forceFill($sessionUpdates)->save();
            $turn->setRawAttributes($lockedTurn->getAttributes(), true);

            return $changes;
        });
    }

    private function draftIsValid(Post $post, string $draft): bool
    {
        $document = $this->parser->parse($draft, $this->workspace->mediaMap($post));

        return Validator::make(
            ['body' => $document->toArray()],
            ['body' => [new ValidRichTextDocument]],
        )->passes();
    }

    /** @param array<string, mixed> $meta */
    private function metaIsValid(array $meta): bool
    {
        return Validator::make($meta, [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'og_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'featured_image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'featured_image_caption' => ['sometimes', 'nullable', 'string', 'max:500'],
        ])->passes();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function fieldChanges(array $before, array $after): array
    {
        $changes = [];

        foreach (WorkspaceManager::META_KEYS as $key) {
            if (! array_key_exists($key, $after)) {
                continue;
            }
            if (! AssistantChangeType::fromMetaKey($key) instanceof AssistantChangeType) {
                continue;
            }
            $old = $before[$key] ?? null;
            $new = $after[$key];

            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /** @param list<AssistantChangeType> $types */
    private function supersede(Post $post, array $types): void
    {
        if ($types === []) {
            return;
        }

        AssistantChange::query()
            ->where('post_id', $post->id)
            ->where('status', AssistantChangeStatus::Proposed)
            ->whereIn('type', $types)
            ->update(['status' => AssistantChangeStatus::Superseded, 'decided_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    private function createChange(AssistantTurn $turn, Post $post, AssistantChangeType $type, array $payload): AssistantChange
    {
        return AssistantChange::create([
            'assistant_turn_id' => $turn->id,
            'post_id' => $post->id,
            'type' => $type,
            'status' => AssistantChangeStatus::Proposed,
            'payload' => $payload,
            'base_lock_version' => $post->lock_version,
        ]);
    }

    /**
     * @param  array<string, mixed>  $sessionUpdates
     */
    private function applyOutlineResult(
        AssistantSession $session,
        AssistantTurn $turn,
        ?string $outline,
        array &$sessionUpdates,
    ): void {
        $step = ($turn->context ?? [])['step'] ?? null;

        if ($outline !== null) {
            if ($session->outline_markdown !== $outline) {
                $sessionUpdates['outline_version'] = $session->outline_version + 1;
            }

            $sessionUpdates['outline_markdown'] = $outline;
            $sessionUpdates['outline_ready'] = $turn->task_type !== AssistantTaskType::Outline || $step !== 'draft';

            return;
        }

        if ($turn->task_type === AssistantTaskType::Outline && $step === 'start') {
            $sessionUpdates['outline_ready'] = false;
        }
    }
}

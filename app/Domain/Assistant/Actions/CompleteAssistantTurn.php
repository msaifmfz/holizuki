<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\BlockDiffer;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

    /** @return list<AssistantChange> */
    public function handle(AssistantTurn $turn, Post $post, ?string $assistantMessage, ?float $costUsd, ?int $durationMs): array
    {
        $newDraft = $this->workspace->readDraft($post);
        $snapshotDraft = $turn->snapshot_draft ?? '';

        if ($newDraft !== $snapshotDraft && ! $this->draftIsValid($post, $newDraft)) {
            $this->failTurn->handle($turn, 'The assistant produced content the editor cannot represent. Nothing was changed — try again.');

            return [];
        }

        $hunks = $this->differ->diff($snapshotDraft, $newDraft);
        $fieldChanges = $this->fieldChanges($turn->snapshot_meta ?? [], $this->workspace->readMeta($post));

        return DB::transaction(function () use ($turn, $post, $assistantMessage, $costUsd, $durationMs, $hunks, $fieldChanges): array {
            $changes = [];

            if ($hunks !== []) {
                $this->supersede($post, [AssistantChangeType::Body]);
            }

            foreach ($hunks as $hunk) {
                $changes[] = $this->createChange($turn, $post, AssistantChangeType::Body, $hunk);
            }

            $fieldTypes = [];

            foreach (array_keys($fieldChanges) as $key) {
                $type = AssistantChangeType::fromMetaKey($key);

                if ($type instanceof AssistantChangeType) {
                    $fieldTypes[$key] = $type;
                }
            }

            $this->supersede($post, array_values($fieldTypes));

            foreach ($fieldTypes as $key => $type) {
                $changes[] = $this->createChange($turn, $post, $type, $fieldChanges[$key]);
            }

            $turn->forceFill([
                'status' => AssistantTurnStatus::Completed,
                'assistant_message' => $assistantMessage,
                'cost_usd' => $costUsd,
                'duration_ms' => $durationMs,
            ])->save();

            $turn->session?->forceFill([
                'status' => AssistantSessionStatus::Idle,
                'turn_started_at' => null,
            ])->save();

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
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

/**
 * An artifact is stale once the post has moved on from the state it was
 * generated against: either the post's lock version changed, or rebuilding the
 * artifact's input context now yields a different hash.
 *
 * Checking many artifacts in one listing is cheap because the context builds
 * themselves are memoized by AuthoringContextCache.
 */
final readonly class AuthoringArtifactFreshness
{
    public function __construct(
        private AuthoringContextFactory $contexts,
        private AuthoringContextCache $cache,
    ) {}

    public function isStale(Post $post, AuthoringArtifact $artifact): bool
    {
        if ($artifact->isStaleFor($post)) {
            return true;
        }

        // Keyed by the artifact, not its task: one task emits artifacts that
        // can differ in type and parent, and those decide which context the
        // artifact is compared against.
        return $this->cache->remember(
            $post,
            'artifact_freshness',
            [$artifact->id],
            fn (): bool => $this->contextHasMovedOn($post, $artifact),
        );
    }

    private function contextHasMovedOn(Post $post, AuthoringArtifact $artifact): bool
    {
        try {
            return match ($artifact->type) {
                AuthoringArtifactType::Plan => $this->hasContextChanged(
                    $artifact,
                    AuthoringTaskKind::Plans,
                    $this->contexts->forPlans($post),
                ),
                AuthoringArtifactType::Draft => $this->hasContextChanged(
                    $artifact,
                    AuthoringTaskKind::Draft,
                    $this->contexts->forDraft($post, $artifact->parent()->firstOrFail()),
                ),
                AuthoringArtifactType::ImageGuidance,
                AuthoringArtifactType::ImageGroupSuggestion => $this->hasContextChanged(
                    $artifact,
                    AuthoringTaskKind::ImageGuidance,
                    $this->contexts->forImageGuidance($post, $artifact->parent()->firstOrFail()),
                ),
                AuthoringArtifactType::ImagePassage => $this->imagePassageIsStale($post, $artifact),
                AuthoringArtifactType::ResearchFinding => $this->hasContextChanged(
                    $artifact,
                    AuthoringTaskKind::Research,
                    $this->contexts->forResearch($post),
                ),
            };
        } catch (LogicException|ModelNotFoundException) {
            return true;
        }
    }

    private function imagePassageIsStale(Post $post, AuthoringArtifact $artifact): bool
    {
        $compositionId = $artifact->payload['composition_id'] ?? null;

        if (! is_string($compositionId)) {
            return true;
        }

        $composition = AuthoringImageComposition::query()
            ->whereBelongsTo($post)
            ->where('public_id', $compositionId)
            ->firstOrFail();

        return $this->hasContextChanged(
            $artifact,
            AuthoringTaskKind::ImagePassages,
            $this->contexts->forImagePassages($post, $composition),
        );
    }

    /** @param array<string, mixed> $context */
    private function hasContextChanged(
        AuthoringArtifact $artifact,
        AuthoringTaskKind $expectedKind,
        array $context,
    ): bool {
        $task = $artifact->task()->firstOrFail();

        return $task->kind !== $expectedKind
            || $task->input_hash !== $this->contexts->hash($expectedKind, $context);
    }
}

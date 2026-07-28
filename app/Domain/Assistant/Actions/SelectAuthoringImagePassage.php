<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Exceptions\StaleAuthoringArtifactException;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SelectAuthoringImagePassage
{
    public function __construct(
        private AuthoringArtifactFreshness $freshness,
        private AuthoringContextFactory $contexts,
    ) {}

    public function handle(
        Post $post,
        AuthoringImageComposition $composition,
        ?AuthoringArtifact $passage,
        string $passageMarkdown,
        User $user,
    ): AuthoringImageComposition {
        return DB::transaction(function () use (
            $post,
            $composition,
            $passage,
            $passageMarkdown,
            $user,
        ): AuthoringImageComposition {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()
                ->withTrashed()
                ->whereKey($post->id)
                ->lockForUpdate()
                ->firstOrFail();
            /** @var AuthoringImageComposition $lockedComposition */
            $lockedComposition = AuthoringImageComposition::query()
                ->whereKey($composition->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedComposition->post_id !== $lockedPost->id
                || $lockedComposition->usage !== AuthoringImageCompositionUsage::Include
                || ! $lockedComposition->media()->exists()
            ) {
                throw ValidationException::withMessages([
                    'composition' => 'The photo composition cannot accept a passage.',
                ]);
            }

            $plan = $lockedComposition->plan()->firstOrFail();

            if ($this->freshness->isStale($lockedPost, $plan)) {
                throw new StaleAuthoringArtifactException;
            }

            $selectedPlanId = AuthoringSelection::query()
                ->whereBelongsTo($lockedPost)
                ->value('selected_plan_id');

            // A plan the author has moved on from is not stale, just no longer
            // the one being written, so it gets its own answer.
            if ($selectedPlanId !== $plan->id) {
                throw ValidationException::withMessages([
                    'plan' => 'Choose this plan before saving photo passages.',
                ]);
            }

            $lockedPassage = null;

            if ($passage instanceof AuthoringArtifact) {
                /** @var AuthoringArtifact $lockedPassage */
                $lockedPassage = AuthoringArtifact::query()->whereKey($passage->id)->firstOrFail();

                if (
                    $lockedPassage->post_id !== $lockedPost->id
                    || $lockedPassage->type !== AuthoringArtifactType::ImagePassage
                    || $lockedPassage->parent_id !== $lockedComposition->plan_id
                    || ($lockedPassage->payload['composition_id'] ?? null) !== $lockedComposition->public_id
                ) {
                    throw ValidationException::withMessages([
                        'selected_passage_id' => 'The selected passage does not belong to this photo composition.',
                    ]);
                }

                if ($this->freshness->isStale($lockedPost, $lockedPassage)) {
                    throw new StaleAuthoringArtifactException;
                }
            }

            $lockedComposition->forceFill([
                'selected_passage_id' => $lockedPassage?->id,
                'selected_by_id' => $user->id,
                'passage_markdown' => trim($passageMarkdown),
                'passage_input_hash' => $this->contexts->hash(
                    AuthoringTaskKind::ImagePassages,
                    $this->contexts->forImagePassages($lockedPost, $lockedComposition),
                ),
            ])->save();

            return $lockedComposition->refresh()->load(['plan', 'media', 'selectedPassage']);
        });
    }
}

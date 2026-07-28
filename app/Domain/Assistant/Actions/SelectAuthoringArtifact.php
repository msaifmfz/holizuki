<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Exceptions\StaleAuthoringArtifactException;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class SelectAuthoringArtifact
{
    public function __construct(private AuthoringArtifactFreshness $freshness) {}

    public function handle(
        Post $post,
        AuthoringArtifact $artifact,
        AuthoringArtifactType $expectedType,
        User $user,
    ): AuthoringSelection {
        return DB::transaction(function () use ($post, $artifact, $expectedType, $user): AuthoringSelection {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()->withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            /** @var AuthoringArtifact $lockedArtifact */
            $lockedArtifact = AuthoringArtifact::query()->whereKey($artifact->id)->firstOrFail();

            if ($lockedArtifact->post_id !== $lockedPost->id || $lockedArtifact->type !== $expectedType) {
                throw new InvalidArgumentException('The selected artifact does not belong to this post or type.');
            }

            if ($this->freshness->isStale($lockedPost, $lockedArtifact)) {
                throw new StaleAuthoringArtifactException;
            }

            $selection = AuthoringSelection::query()->firstOrNew(['post_id' => $lockedPost->id]);
            $selection->forceFill(['selected_by_id' => $user->id]);

            if ($expectedType === AuthoringArtifactType::Plan) {
                $selection->forceFill([
                    'selected_plan_id' => $lockedArtifact->id,
                    'selected_draft_id' => null,
                ]);
            } else {
                if ($lockedArtifact->parent_id === null) {
                    throw new InvalidArgumentException('Draft variants must belong to a plan.');
                }

                $this->ensureDraftIsCurrent($lockedPost, $lockedArtifact);
                $selection->forceFill([
                    'selected_plan_id' => $lockedArtifact->parent_id,
                    'selected_draft_id' => $lockedArtifact->id,
                ]);
            }

            $selection->save();

            return $selection->refresh();
        });
    }

    private function ensureDraftIsCurrent(Post $post, AuthoringArtifact $draft): void
    {
        $selectedPlanId = AuthoringSelection::query()
            ->whereBelongsTo($post)
            ->value('selected_plan_id');

        if ($selectedPlanId !== $draft->parent_id) {
            throw ValidationException::withMessages([
                'draft' => 'This draft does not belong to the currently selected plan.',
            ]);
        }
    }
}

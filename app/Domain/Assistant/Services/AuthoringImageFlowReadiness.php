<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AuthoringImageFlowReadiness
{
    public function __construct(
        private AuthoringContextFactory $contexts,
        private AuthoringStoryPhotoEligibility $storyPhotos,
        private AuthoringPhotoFlowContext $photoFlow,
    ) {}

    public function ensureReadyForDraft(Post $post, AuthoringArtifact $plan): void
    {
        if ($plan->post_id !== $post->id || $plan->type !== AuthoringArtifactType::Plan) {
            throw new LogicException('The selected authoring plan does not belong to this post.');
        }

        $eligibleMedia = $this->storyPhotos->forPost($post);

        if ($eligibleMedia->isEmpty()) {
            return;
        }

        $compositions = $this->photoFlow->eligibleCompositions($post, $plan);
        $assignedMediaIds = [];

        foreach ($compositions as $composition) {
            foreach ($composition->media as $media) {
                $assignedMediaIds[] = $media->id;
            }
        }

        $assignedMediaIds = array_values(array_unique($assignedMediaIds));
        $eligibleMediaIds = $eligibleMedia
            ->map(fn (PostMedia $media): int => $media->id)
            ->values()
            ->all();

        sort($assignedMediaIds);
        sort($eligibleMediaIds);

        if ($assignedMediaIds !== $eligibleMediaIds) {
            throw ValidationException::withMessages([
                'photo_flow' => 'Choose include or skip for every story photo before drafting.',
            ]);
        }

        $usedMediaIds = $post->draft()->firstOrFail()->body?->referencedMediaIds() ?? [];
        $requiredMediaIds = $eligibleMedia
            ->filter(fn (PostMedia $media): bool => $media->story_priority
                === PostMediaStoryPriority::Required
                || in_array($media->id, $usedMediaIds, true))
            ->map(fn (PostMedia $media): int => $media->id)
            ->values()
            ->all();
        $includedMediaIds = [];

        foreach ($compositions as $composition) {
            if ($composition->usage !== AuthoringImageCompositionUsage::Include) {
                continue;
            }

            foreach ($composition->media as $media) {
                $includedMediaIds[] = $media->id;
            }
        }

        if (array_diff($requiredMediaIds, $includedMediaIds) !== []) {
            throw ValidationException::withMessages([
                'photo_flow' => 'Every required story photo must be included before drafting.',
            ]);
        }

        $hasIncompletePassage = $compositions
            ->contains(fn (AuthoringImageComposition $composition): bool => $composition->usage
                === AuthoringImageCompositionUsage::Include
                && blank($composition->passage_markdown));

        if ($hasIncompletePassage) {
            throw ValidationException::withMessages([
                'photo_flow' => 'Choose an AI passage or write your own for every included photo block.',
            ]);
        }

        $hasStalePassage = $compositions
            ->filter(fn (AuthoringImageComposition $composition): bool => $composition->usage
                === AuthoringImageCompositionUsage::Include)
            ->contains(fn (AuthoringImageComposition $composition): bool => $this
                ->passageIsStale($post, $composition));

        if ($hasStalePassage) {
            throw ValidationException::withMessages([
                'photo_flow' => 'Review and save each photo passage again after changing its photo context.',
            ]);
        }
    }

    public function passageIsStale(
        Post $post,
        AuthoringImageComposition $composition,
    ): bool {
        if (
            $composition->usage !== AuthoringImageCompositionUsage::Include
            || blank($composition->passage_markdown)
            || ! $this->storyPhotos->compositionIsEligible($post, $composition)
        ) {
            return false;
        }

        return $composition->passage_input_hash
            !== $this->contexts->hash(
                AuthoringTaskKind::ImagePassages,
                $this->contexts->forImagePassages($post, $composition),
            );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class AuthoringArtifactIndex
{
    private const int PLAN_LIMIT = 30;

    private const int DRAFT_LIMIT = 30;

    private const int RESEARCH_FINDING_LIMIT = 60;

    private const int IMAGE_GUIDANCE_LIMIT = 20;

    private const int IMAGE_GROUP_SUGGESTION_LIMIT = 10;

    private const int IMAGE_PASSAGE_LIMIT_PER_COMPOSITION = 3;

    private const int IMAGE_COMPOSITION_LIMIT = 20;

    public function __construct(
        private AuthoringStoryPhotoEligibility $storyPhotos,
    ) {}

    /**
     * @return array{
     *     plans: Collection<int, AuthoringArtifact>,
     *     drafts: Collection<int, AuthoringArtifact>,
     *     research_findings: Collection<int, AuthoringArtifact>,
     *     image_guidance: Collection<int, AuthoringArtifact>,
     *     image_group_suggestions: Collection<int, AuthoringArtifact>,
     *     image_passages: Collection<int, AuthoringArtifact>,
     *     image_compositions: Collection<int, AuthoringImageComposition>
     * }
     */
    public function for(Post $post, ?AuthoringSelection $selection): array
    {
        $imageCompositions = $this->imageCompositions($post, $selection);
        $imageGuidance = $this->latestArtifactGroup(
            $post,
            AuthoringArtifactType::ImageGuidance,
            $selection?->selected_plan_id,
            self::IMAGE_GUIDANCE_LIMIT,
        );

        return [
            'plans' => $this->plans($post, $selection),
            'drafts' => $this->drafts($post, $selection),
            'research_findings' => $this->recentArtifacts(
                $post,
                AuthoringArtifactType::ResearchFinding,
                self::RESEARCH_FINDING_LIMIT,
            ),
            'image_guidance' => $imageGuidance,
            'image_group_suggestions' => $this->artifactsInGroup(
                $post,
                AuthoringArtifactType::ImageGroupSuggestion,
                $selection?->selected_plan_id,
                $imageGuidance->first()?->group_id,
                self::IMAGE_GROUP_SUGGESTION_LIMIT,
            ),
            'image_passages' => $this->latestImagePassages(
                $post,
                $selection?->selected_plan_id,
                $imageCompositions,
            ),
            'image_compositions' => $imageCompositions,
        ];
    }

    /** @return Collection<int, AuthoringArtifact> */
    private function plans(Post $post, ?AuthoringSelection $selection): Collection
    {
        $plans = $this->recentArtifacts(
            $post,
            AuthoringArtifactType::Plan,
            self::PLAN_LIMIT,
        );

        return $this->includeSelectedArtifact(
            $plans,
            $post,
            AuthoringArtifactType::Plan,
            $selection?->selected_plan_id,
        );
    }

    /** @return Collection<int, AuthoringArtifact> */
    private function drafts(Post $post, ?AuthoringSelection $selection): Collection
    {
        if ($selection?->selected_plan_id === null) {
            return $this->emptyArtifactCollection();
        }

        $drafts = $this->recentArtifacts(
            $post,
            AuthoringArtifactType::Draft,
            self::DRAFT_LIMIT,
            $selection->selected_plan_id,
        );

        return $this->includeSelectedArtifact(
            $drafts,
            $post,
            AuthoringArtifactType::Draft,
            $selection->selected_draft_id,
        );
    }

    /** @return Collection<int, AuthoringArtifact> */
    private function recentArtifacts(
        Post $post,
        AuthoringArtifactType $type,
        int $limit,
        ?int $parentId = null,
    ): Collection {
        return AuthoringArtifact::query()
            ->whereBelongsTo($post)
            ->where('type', $type)
            ->when(
                $parentId !== null,
                fn (Builder $query): Builder => $query->where('parent_id', $parentId),
            )
            ->with('parent')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, AuthoringArtifact>  $artifacts
     * @return Collection<int, AuthoringArtifact>
     */
    private function includeSelectedArtifact(
        Collection $artifacts,
        Post $post,
        AuthoringArtifactType $type,
        ?int $selectedArtifactId,
    ): Collection {
        if (
            $selectedArtifactId === null
            || $artifacts->contains(fn (AuthoringArtifact $artifact): bool => $artifact->id
                === $selectedArtifactId)
        ) {
            return $artifacts;
        }

        $selectedArtifact = AuthoringArtifact::query()
            ->whereBelongsTo($post)
            ->whereKey($selectedArtifactId)
            ->where('type', $type)
            ->with('parent')
            ->first();

        return $selectedArtifact instanceof AuthoringArtifact
            ? $artifacts->push($selectedArtifact)
            : $artifacts;
    }

    /** @return Collection<int, AuthoringArtifact> */
    private function latestArtifactGroup(
        Post $post,
        AuthoringArtifactType $type,
        ?int $parentId,
        int $limit,
    ): Collection {
        if ($parentId === null) {
            return $this->emptyArtifactCollection();
        }

        $artifacts = $this->recentArtifacts($post, $type, $limit, $parentId);
        $groupId = $artifacts->first()?->group_id;

        return is_string($groupId)
            ? $artifacts->where('group_id', $groupId)->values()
            : $this->emptyArtifactCollection();
    }

    /** @return Collection<int, AuthoringArtifact> */
    private function artifactsInGroup(
        Post $post,
        AuthoringArtifactType $type,
        ?int $parentId,
        ?string $groupId,
        int $limit,
    ): Collection {
        if ($parentId === null || $groupId === null) {
            return $this->emptyArtifactCollection();
        }

        return AuthoringArtifact::query()
            ->whereBelongsTo($post)
            ->where('parent_id', $parentId)
            ->where('type', $type)
            ->where('group_id', $groupId)
            ->with('parent')
            ->oldest('position')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, AuthoringImageComposition>  $compositions
     * @return Collection<int, AuthoringArtifact>
     */
    private function latestImagePassages(
        Post $post,
        ?int $parentId,
        Collection $compositions,
    ): Collection {
        if ($parentId === null) {
            return $this->emptyArtifactCollection();
        }

        $passages = $this->emptyArtifactCollection();

        // Bounded by IMAGE_COMPOSITION_LIMIT. A single combined query cannot
        // replace this without a per-composition window function, because one
        // global row limit truncates whichever compositions sort last.
        foreach ($compositions as $composition) {
            $latestPassages = AuthoringArtifact::query()
                ->whereBelongsTo($post)
                ->where('parent_id', $parentId)
                ->where('type', AuthoringArtifactType::ImagePassage)
                ->where('payload->composition_id', $composition->public_id)
                ->with('parent')
                ->latest('id')
                ->limit(self::IMAGE_PASSAGE_LIMIT_PER_COMPOSITION)
                ->get();
            $groupId = $latestPassages->first()?->group_id;

            if (is_string($groupId)) {
                foreach ($latestPassages->where('group_id', $groupId) as $passage) {
                    $passages->push($passage);
                }
            }
        }

        return $passages;
    }

    /** @return Collection<int, AuthoringArtifact> */
    private function emptyArtifactCollection(): Collection
    {
        return new Collection;
    }

    /** @return Collection<int, AuthoringImageComposition> */
    private function imageCompositions(
        Post $post,
        ?AuthoringSelection $selection,
    ): Collection {
        if ($selection?->selected_plan_id === null) {
            return new Collection;
        }

        return AuthoringImageComposition::query()
            ->whereBelongsTo($post)
            ->where('plan_id', $selection->selected_plan_id)
            ->whereHas('media')
            ->with(['plan', 'media', 'selectedPassage'])
            ->oldest('position')
            ->limit(self::IMAGE_COMPOSITION_LIMIT)
            ->get()
            ->filter(
                fn (AuthoringImageComposition $composition): bool => $this
                    ->storyPhotos
                    ->compositionIsEligible($post, $composition),
            )
            ->values();
    }
}

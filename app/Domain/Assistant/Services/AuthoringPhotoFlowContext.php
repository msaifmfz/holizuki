<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Describes the author's arrangement of story photos for a plan: which photos
 * are in, in what order, under which section, and with what approved writing.
 *
 * Two audiences need different amounts of it. Drafting needs the full picture
 * including approved passage text, since the draft has to reproduce it exactly.
 * Writing a passage needs only an outline of the surrounding blocks, so the
 * agent can pace one passage against its neighbours without seeing the text it
 * is supposed to be writing independently.
 */
final readonly class AuthoringPhotoFlowContext
{
    public function __construct(
        private AuthoringStoryPhotoEligibility $storyPhotos,
        private AuthoringContextCache $cache,
    ) {}

    /**
     * The full arrangement, with approved writing, for generating a draft.
     *
     * @return array<string, mixed>
     */
    public function forDraft(Post $post, AuthoringArtifact $plan): array
    {
        $compositions = $this->eligibleCompositions($post, $plan);

        return [
            'photo_flow' => $compositions
                ->map(function (AuthoringImageComposition $composition): array {
                    $data = $this->compositionData($composition);

                    // A skipped block has no approved writing to reproduce.
                    if ($composition->usage !== AuthoringImageCompositionUsage::Include) {
                        unset($data['selected_passage_id'], $data['passage_markdown']);
                    }

                    return $data;
                })
                ->all(),
            'included_media_ids' => $this->compositionMediaIds($compositions, 'include'),
            'excluded_media_ids' => $this->compositionMediaIds($compositions, 'exclude'),
        ];
    }

    /**
     * An outline of the arrangement plus the immediate neighbours of the block
     * being written, under keys distinct from the draft context so the two are
     * never mistaken for each other.
     *
     * @param  array<int, array<string, mixed>>  $guidanceByMediaId
     * @return array<string, mixed>
     */
    public function forPassages(
        Post $post,
        AuthoringArtifact $plan,
        AuthoringImageComposition $currentComposition,
        array $guidanceByMediaId,
    ): array {
        $compositions = $this->eligibleCompositions($post, $plan);
        $outline = [];
        $includedFlow = [];
        $includedCompositions = [];
        $includedPosition = 0;
        $currentFlow = null;

        foreach ($compositions as $composition) {
            $isIncluded = $composition->usage === AuthoringImageCompositionUsage::Include;

            if ($isIncluded) {
                $includedPosition++;
            }

            $flowItem = [
                'id' => $composition->public_id,
                'usage' => $composition->usage->value,
                'position' => $composition->position,
                'included_position' => $isIncluded ? $includedPosition : null,
                'section_heading' => $composition->section_heading,
                'media_ids' => $composition->media
                    ->map(fn (PostMedia $media): int => $media->id)
                    ->values()
                    ->all(),
            ];
            $outline[] = $flowItem;

            if ($isIncluded) {
                $includedFlow[] = $flowItem;
                $includedCompositions[] = $composition;
            }

            if ($composition->id === $currentComposition->id) {
                $currentFlow = $flowItem;
            }
        }

        if (! is_array($currentFlow) || ! is_int($currentFlow['included_position'])) {
            throw new LogicException('The image composition is not in the selected photo flow.');
        }

        $currentIncludedOffset = $currentFlow['included_position'] - 1;

        return [
            'photo_flow_outline' => $outline,
            'current_photo_flow' => [
                'position' => $currentFlow['position'],
                'included_position' => $currentFlow['included_position'],
                'included_count' => count($includedFlow),
                'previous_included' => $this->neighborContext(
                    $includedFlow[$currentIncludedOffset - 1] ?? null,
                    $includedCompositions[$currentIncludedOffset - 1] ?? null,
                    $guidanceByMediaId,
                ),
                'next_included' => $this->neighborContext(
                    $includedFlow[$currentIncludedOffset + 1] ?? null,
                    $includedCompositions[$currentIncludedOffset + 1] ?? null,
                    $guidanceByMediaId,
                ),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function compositionData(AuthoringImageComposition $composition): array
    {
        $composition->loadMissing(['media', 'selectedPassage']);

        return [
            'id' => $composition->public_id,
            'usage' => $composition->usage->value,
            'section_heading' => $composition->section_heading,
            'position' => $composition->position,
            'media' => $composition->media->map(fn (PostMedia $media): array => [
                'id' => $media->id,
                'display_name' => $media->display_name,
                'alt_text' => $media->alt_text,
                'caption' => $media->caption,
                'story_priority' => $media->story_priority->value,
                'story_notes' => $media->story_notes,
                'updated_at' => $media->updated_at?->toISOString(),
            ])->all(),
            'selected_passage_id' => $composition->selectedPassage?->public_id,
            'passage_markdown' => $composition->passage_markdown,
        ];
    }

    /**
     * The eligible photo blocks for a plan, in author-chosen order. Identical
     * for every composition in the same plan, so building it once per listing
     * keeps per-composition staleness checks cheap.
     *
     * @return Collection<int, AuthoringImageComposition>
     */
    public function eligibleCompositions(Post $post, AuthoringArtifact $plan): Collection
    {
        return $this->cache->remember(
            $post,
            'eligible_compositions',
            [$plan->id],
            fn (): Collection => AuthoringImageComposition::query()
                ->whereBelongsTo($post)
                ->where('plan_id', $plan->id)
                ->whereHas('media')
                ->with(['media', 'selectedPassage'])
                ->oldest('position')
                ->get()
                ->filter(
                    fn (AuthoringImageComposition $composition): bool => $this
                        ->storyPhotos
                        ->compositionIsEligible($post, $composition),
                )
                ->values(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $flowItem
     * @param  array<int, array<string, mixed>>  $guidanceByMediaId
     * @return array<string, mixed>|null
     */
    private function neighborContext(
        ?array $flowItem,
        ?AuthoringImageComposition $composition,
        array $guidanceByMediaId,
    ): ?array {
        if ($flowItem === null || ! $composition instanceof AuthoringImageComposition) {
            return null;
        }

        return [
            ...$flowItem,
            'media' => $composition->media->map(function (PostMedia $media) use (
                $guidanceByMediaId,
            ): array {
                $guidance = $guidanceByMediaId[$media->id] ?? [];

                return [
                    'id' => $media->id,
                    'display_name' => $media->display_name,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'story_notes' => $media->story_notes,
                    'narrative_role' => is_string($guidance['narrative_role'] ?? null)
                        ? $guidance['narrative_role']
                        : null,
                    'writing_angle' => is_string($guidance['writing_angle'] ?? null)
                        ? $guidance['writing_angle']
                        : null,
                ];
            })->all(),
        ];
    }

    /**
     * @param  Collection<int, AuthoringImageComposition>  $compositions
     * @return list<int>
     */
    private function compositionMediaIds(Collection $compositions, string $usage): array
    {
        $mediaIds = [];

        foreach ($compositions as $composition) {
            if ($composition->usage->value !== $usage) {
                continue;
            }

            foreach ($composition->media as $media) {
                $mediaIds[] = $media->id;
            }
        }

        return $mediaIds;
    }
}

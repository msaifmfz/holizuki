<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Exceptions\StaleAuthoringArtifactException;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SyncAuthoringImageFlow
{
    public function __construct(private AuthoringArtifactFreshness $freshness) {}

    /**
     * @param  list<array{id?: string|null, usage: string, section_heading?: string|null, media_ids: list<int>}>  $blocks
     * @return Collection<int, AuthoringImageComposition>
     */
    public function handle(
        Post $post,
        AuthoringArtifact $plan,
        User $user,
        array $blocks,
    ): Collection {
        return DB::transaction(function () use ($post, $plan, $user, $blocks): Collection {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()
                ->withTrashed()
                ->whereKey($post->id)
                ->lockForUpdate()
                ->firstOrFail();
            /** @var AuthoringArtifact $lockedPlan */
            $lockedPlan = AuthoringArtifact::query()->whereKey($plan->id)->firstOrFail();
            $this->ensureSelectedPlan($lockedPost, $lockedPlan);
            $sections = $this->planSections($lockedPlan);
            $eligibleMedia = $this->eligibleMedia($lockedPost);
            $usedMediaIds = $lockedPost->draft()->firstOrFail()->body?->referencedMediaIds() ?? [];
            $requiredMediaIds = [];
            $eligibleMediaIds = [];

            foreach ($eligibleMedia as $media) {
                $eligibleMediaIds[] = $media->id;

                if (
                    $media->story_priority === PostMediaStoryPriority::Required
                    || in_array($media->id, $usedMediaIds, true)
                ) {
                    $requiredMediaIds[] = $media->id;
                }
            }

            $existing = AuthoringImageComposition::query()
                ->whereBelongsTo($lockedPost)
                ->where('plan_id', $lockedPlan->id)
                ->with('media')
                ->lockForUpdate()
                ->get()
                ->keyBy('public_id');

            $this->validateBlocks(
                $lockedPost,
                $lockedPlan,
                $blocks,
                $eligibleMediaIds,
                $requiredMediaIds,
                $sections,
                $existing,
            );

            $providedCompositionIds = [];

            foreach ($blocks as $index => $block) {
                $publicId = $block['id'] ?? null;

                if (! is_string($publicId)) {
                    continue;
                }

                if (
                    in_array($publicId, $providedCompositionIds, true)
                    || ! $existing->has($publicId)
                ) {
                    throw ValidationException::withMessages([
                        "blocks.{$index}.id" => 'This photo block is duplicated or no longer available.',
                    ]);
                }

                $providedCompositionIds[] = $publicId;
            }

            $writtenCompositionWasRemoved = $existing
                ->contains(fn (AuthoringImageComposition $composition): bool => filled($composition->passage_markdown)
                    && ! in_array($composition->public_id, $providedCompositionIds, true));

            if ($writtenCompositionWasRemoved) {
                throw ValidationException::withMessages([
                    'blocks' => 'Keep photo blocks with saved writing separate. You can change their order or section and review the writing again.',
                ]);
            }

            AuthoringImageComposition::query()
                ->whereBelongsTo($lockedPost)
                ->where('plan_id', $lockedPlan->id)
                ->increment('position', 100);
            DB::table('assistant_authoring_image_media')
                ->where('plan_id', $lockedPlan->id)
                ->delete();
            $retainedIds = [];

            foreach ($blocks as $position => $block) {
                $publicId = $block['id'] ?? null;
                $composition = is_string($publicId) ? $existing->get($publicId) : null;

                if (! $composition instanceof AuthoringImageComposition) {
                    $composition = new AuthoringImageComposition([
                        'public_id' => (string) Str::uuid(),
                        'post_id' => $lockedPost->id,
                        'plan_id' => $lockedPlan->id,
                    ]);
                }

                $usage = AuthoringImageCompositionUsage::from($block['usage']);
                $sectionHeading = $usage === AuthoringImageCompositionUsage::Include
                    ? ($block['section_heading'] ?? null)
                    : null;
                $mediaIds = $block['media_ids'];
                $currentMediaIds = $composition->exists
                    ? $composition->media->pluck('id')->all()
                    : [];
                $changed = $composition->exists && (
                    $composition->usage !== $usage
                    || $composition->section_heading !== $sectionHeading
                    || $currentMediaIds !== $mediaIds
                    || $composition->position !== $position + 1
                );

                $composition->forceFill([
                    'usage' => $usage,
                    'section_heading' => $sectionHeading,
                    'position' => $position + 1,
                    'selected_by_id' => $user->id,
                    'selected_passage_id' => $changed ? null : $composition->selected_passage_id,
                    'passage_markdown' => $composition->passage_markdown,
                    'passage_input_hash' => $composition->passage_input_hash,
                ])->save();
                $composition->media()->sync($this->pivotData($lockedPlan, $mediaIds));
                $retainedIds[] = $composition->id;
            }

            AuthoringImageComposition::query()
                ->whereBelongsTo($lockedPost)
                ->where('plan_id', $lockedPlan->id)
                ->when(
                    $retainedIds !== [],
                    fn (Builder $query): Builder => $query->whereNotIn('id', $retainedIds),
                )
                ->delete();

            return AuthoringImageComposition::query()
                ->whereBelongsTo($lockedPost)
                ->where('plan_id', $lockedPlan->id)
                ->with(['plan', 'media', 'selectedPassage'])
                ->oldest('position')
                ->get();
        });
    }

    private function ensureSelectedPlan(Post $post, AuthoringArtifact $plan): void
    {
        if (
            $plan->post_id !== $post->id
            || $plan->type !== AuthoringArtifactType::Plan
            || $this->freshness->isStale($post, $plan)
        ) {
            throw new StaleAuthoringArtifactException;
        }

        $selectedPlanId = AuthoringSelection::query()
            ->whereBelongsTo($post)
            ->value('selected_plan_id');

        if ($selectedPlanId !== $plan->id) {
            throw ValidationException::withMessages([
                'plan' => 'Choose this plan before arranging story photos.',
            ]);
        }
    }

    /** @return list<string> */
    private function planSections(AuthoringArtifact $plan): array
    {
        $sections = $plan->payload['sections'] ?? null;

        if (! is_array($sections) || ! array_is_list($sections)) {
            throw ValidationException::withMessages(['plan' => 'The selected plan has no valid sections.']);
        }

        return array_values(array_filter($sections, is_string(...)));
    }

    /** @return Collection<int, PostMedia> */
    private function eligibleMedia(Post $post): Collection
    {
        return PostMedia::query()
            ->whereBelongsTo($post)
            ->where('status', PostMediaStatus::Ready)
            ->where('role', '!=', PostMediaRole::Hero)
            ->where('placement', '!=', PostMediaPlacement::Featured)
            ->oldest('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  list<array{id?: string|null, usage: string, section_heading?: string|null, media_ids: list<int>}>  $blocks
     * @param  list<int>  $eligibleMediaIds
     * @param  list<int>  $requiredMediaIds
     * @param  list<string>  $sections
     * @param  Collection<string, AuthoringImageComposition>  $existing
     */
    private function validateBlocks(
        Post $post,
        AuthoringArtifact $plan,
        array $blocks,
        array $eligibleMediaIds,
        array $requiredMediaIds,
        array $sections,
        Collection $existing,
    ): void {
        $assignedMediaIds = [];
        $includedMediaIds = [];
        $suggestedGroups = $this->currentSuggestedGroups($post, $plan);

        foreach ($blocks as $index => $block) {
            $usage = AuthoringImageCompositionUsage::from($block['usage']);
            $mediaIds = $block['media_ids'];

            if (array_diff($mediaIds, $eligibleMediaIds) !== []) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.media_ids" => 'A story-photo block contains an unavailable or featured image.',
                ]);
            }

            if (array_intersect($assignedMediaIds, $mediaIds) !== []) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.media_ids" => 'Each story photo may appear in only one block.',
                ]);
            }

            if ($usage === AuthoringImageCompositionUsage::Exclude && count($mediaIds) !== 1) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.media_ids" => 'Excluded photos must remain individual.',
                ]);
            }

            $publicId = $block['id'] ?? null;
            $existingComposition = is_string($publicId)
                ? $existing->get($publicId)
                : null;
            $matchesExistingGroup = $existingComposition instanceof AuthoringImageComposition
                && $existingComposition->media->pluck('id')->all() === $mediaIds;

            if (
                count($mediaIds) > 1
                && ! $matchesExistingGroup
                && ! in_array($mediaIds, $suggestedGroups, true)
            ) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.media_ids" => 'Photo groups must match a current AI group suggestion.',
                ]);
            }

            if (
                $usage === AuthoringImageCompositionUsage::Include
                && ! in_array($block['section_heading'] ?? null, $sections, true)
            ) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.section_heading" => 'Choose an existing section from the selected plan.',
                ]);
            }

            $assignedMediaIds = [...$assignedMediaIds, ...$mediaIds];

            if ($usage === AuthoringImageCompositionUsage::Include) {
                $includedMediaIds = [...$includedMediaIds, ...$mediaIds];
            }
        }

        if (array_diff($requiredMediaIds, $includedMediaIds) !== []) {
            throw ValidationException::withMessages([
                'blocks' => 'Every required story photo must be included before continuing.',
            ]);
        }
    }

    /** @return list<list<int>> */
    private function currentSuggestedGroups(Post $post, AuthoringArtifact $plan): array
    {
        $latestGuidance = AuthoringArtifact::query()
            ->whereBelongsTo($post)
            ->where('parent_id', $plan->id)
            ->where('type', AuthoringArtifactType::ImageGuidance)
            ->latest('id')
            ->first();

        if (
            ! $latestGuidance instanceof AuthoringArtifact
            || $this->freshness->isStale($post, $latestGuidance)
        ) {
            return [];
        }

        $groups = [];
        $artifacts = AuthoringArtifact::query()
            ->whereBelongsTo($post)
            ->where('parent_id', $plan->id)
            ->where('type', AuthoringArtifactType::ImageGroupSuggestion)
            ->where('group_id', $latestGuidance->group_id)
            ->latest('id')
            ->get();

        foreach ($artifacts as $artifact) {
            if ($this->freshness->isStale($post, $artifact)) {
                continue;
            }

            $payloadMediaIds = $artifact->payload['media_ids'] ?? null;

            if (! is_array($payloadMediaIds)) {
                continue;
            }

            if (! array_is_list($payloadMediaIds)) {
                continue;
            }

            $mediaIds = [];

            foreach ($payloadMediaIds as $mediaId) {
                if (! is_int($mediaId)) {
                    continue 2;
                }

                $mediaIds[] = $mediaId;
            }

            $groups[] = $mediaIds;
        }

        return $groups;
    }

    /**
     * @param  list<int>  $mediaIds
     * @return array<int, array{plan_id: int, position: int}>
     */
    private function pivotData(AuthoringArtifact $plan, array $mediaIds): array
    {
        $pivot = [];

        foreach ($mediaIds as $position => $mediaId) {
            $pivot[$mediaId] = [
                'plan_id' => $plan->id,
                'position' => $position + 1,
            ];
        }

        return $pivot;
    }
}

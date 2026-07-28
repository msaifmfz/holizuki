<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Normalizers;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Assistant\ValueObjects\AuthoringArtifactPayload;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class AuthoringImageGuidanceNormalizer implements AuthoringOutputNormalizer
{
    /** @return list<AuthoringArtifactPayload> */
    public function normalize(AuthoringTask $task, Post $post, AssistantOutput $output): array
    {
        $allowedMediaIds = AssistantOutput::integerList($task->context['media_ids'] ?? []);
        $planId = $task->context['plan_artifact_id'] ?? null;
        $planPayload = $task->context['plan_payload'] ?? null;
        $sections = is_array($planPayload)
            ? AssistantOutput::stringList($planPayload['sections'] ?? null, 'plan.sections')
            : [];

        if (! is_int($planId) || $sections === [] || $allowedMediaIds === []) {
            throw ValidationException::withMessages([
                'images' => 'The image guidance task no longer has a valid plan and photos.',
            ]);
        }

        $validated = Validator::make($output->all(), [
            'photo_guidance' => ['required', 'array', 'size:'.count($allowedMediaIds)],
            'photo_guidance.*.media_id' => ['required', 'integer', 'distinct'],
            'photo_guidance.*.suggested_name' => ['required', 'string', 'max:255'],
            'photo_guidance.*.alt_text' => ['required', 'string', 'max:255'],
            'photo_guidance.*.caption' => ['present', 'nullable', 'string', 'max:500'],
            'photo_guidance.*.recommendation' => ['required', Rule::in(['include', 'skip'])],
            'photo_guidance.*.section_heading' => ['required', 'string', Rule::in($sections)],
            'photo_guidance.*.narrative_role' => ['required', 'string', 'max:500'],
            'photo_guidance.*.writing_angle' => ['required', 'string', 'max:1000'],
            'photo_guidance.*.key_points' => ['required', 'array', 'min:2', 'max:5'],
            'photo_guidance.*.key_points.*' => ['required', 'string', 'max:500'],
            'photo_guidance.*.transition_in' => ['present', 'nullable', 'string', 'max:1000'],
            'photo_guidance.*.transition_out' => ['present', 'nullable', 'string', 'max:1000'],
            'photo_guidance.*.rationale' => ['required', 'string', 'max:1500'],
            'photo_guidance.*.questions_for_author' => ['present', 'array', 'max:5'],
            'photo_guidance.*.questions_for_author.*' => ['required', 'string', 'max:500'],
            'group_suggestions' => ['present', 'array', 'max:10'],
            'group_suggestions.*.media_ids' => ['required', 'array', 'min:2', 'max:20'],
            'group_suggestions.*.media_ids.*' => ['required', 'integer'],
            'group_suggestions.*.section_heading' => ['required', 'string', Rule::in($sections)],
            'group_suggestions.*.sequence_rationale' => ['required', 'string', 'max:1500'],
        ])->validate();

        $payloads = $this->guidancePayloads(
            $task,
            AssistantOutput::objectList($validated['photo_guidance'] ?? null, 'photo_guidance'),
            $allowedMediaIds,
            $planId,
        );

        return [
            ...$payloads,
            ...$this->groupSuggestionPayloads(
                AssistantOutput::objectList(
                    $validated['group_suggestions'] ?? null,
                    'group_suggestions',
                ),
                $allowedMediaIds,
                $planId,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $guidanceItems
     * @param  list<int>  $allowedMediaIds
     * @return list<AuthoringArtifactPayload>
     */
    private function guidancePayloads(
        AuthoringTask $task,
        array $guidanceItems,
        array $allowedMediaIds,
        int $planId,
    ): array {
        $returnedMediaIds = [];
        $payloads = [];

        foreach ($guidanceItems as $index => $guidance) {
            $mediaId = AssistantOutput::integerIn(
                $guidance,
                'media_id',
                "photo_guidance.{$index}.media_id",
            );
            AssistantOutput::ensureKnownIds(
                [$mediaId],
                $allowedMediaIds,
                "photo_guidance.{$index}.media_id",
            );
            $returnedMediaIds[] = $mediaId;
            $recommendation = AssistantOutput::stringIn(
                $guidance,
                'recommendation',
                "photo_guidance.{$index}.recommendation",
            );

            if ($this->isRequiredMedia($task, $mediaId) && $recommendation !== 'include') {
                throw ValidationException::withMessages([
                    "photo_guidance.{$index}.recommendation" => 'Required photos must be recommended for inclusion.',
                ]);
            }

            $payloads[] = new AuthoringArtifactPayload(
                type: AuthoringArtifactType::ImageGuidance,
                parentId: $planId,
                position: $index + 1,
                payload: [
                    'media_id' => $mediaId,
                    'suggested_name' => AssistantOutput::stringIn(
                        $guidance,
                        'suggested_name',
                        "photo_guidance.{$index}.suggested_name",
                    ),
                    'alt_text' => AssistantOutput::stringIn(
                        $guidance,
                        'alt_text',
                        "photo_guidance.{$index}.alt_text",
                    ),
                    'caption' => AssistantOutput::nullableStringIn(
                        $guidance,
                        'caption',
                        "photo_guidance.{$index}.caption",
                    ),
                    'recommendation' => $recommendation,
                    'section_heading' => AssistantOutput::stringIn(
                        $guidance,
                        'section_heading',
                        "photo_guidance.{$index}.section_heading",
                    ),
                    'narrative_role' => AssistantOutput::stringIn(
                        $guidance,
                        'narrative_role',
                        "photo_guidance.{$index}.narrative_role",
                    ),
                    'writing_angle' => AssistantOutput::stringIn(
                        $guidance,
                        'writing_angle',
                        "photo_guidance.{$index}.writing_angle",
                    ),
                    'key_points' => AssistantOutput::stringList(
                        $guidance['key_points'] ?? null,
                        "photo_guidance.{$index}.key_points",
                    ),
                    'transition_in' => AssistantOutput::nullableStringIn(
                        $guidance,
                        'transition_in',
                        "photo_guidance.{$index}.transition_in",
                    ),
                    'transition_out' => AssistantOutput::nullableStringIn(
                        $guidance,
                        'transition_out',
                        "photo_guidance.{$index}.transition_out",
                    ),
                    'rationale' => AssistantOutput::stringIn(
                        $guidance,
                        'rationale',
                        "photo_guidance.{$index}.rationale",
                    ),
                    'questions_for_author' => AssistantOutput::stringList(
                        $guidance['questions_for_author'] ?? null,
                        "photo_guidance.{$index}.questions_for_author",
                    ),
                ],
            );
        }

        $expectedMediaIds = $allowedMediaIds;
        sort($expectedMediaIds);
        sort($returnedMediaIds);

        if ($returnedMediaIds !== $expectedMediaIds) {
            throw ValidationException::withMessages([
                'photo_guidance' => 'Guidance must cover every available story photo exactly once.',
            ]);
        }

        return $payloads;
    }

    /**
     * @param  list<array<string, mixed>>  $groupSuggestions
     * @param  list<int>  $allowedMediaIds
     * @return list<AuthoringArtifactPayload>
     */
    private function groupSuggestionPayloads(
        array $groupSuggestions,
        array $allowedMediaIds,
        int $planId,
    ): array {
        $groupedMediaIds = [];
        $payloads = [];

        foreach ($groupSuggestions as $index => $group) {
            $mediaIds = AssistantOutput::strictIntegerList(
                $group['media_ids'] ?? null,
                "group_suggestions.{$index}.media_ids",
            );

            if (count($mediaIds) !== count(array_unique($mediaIds))) {
                throw ValidationException::withMessages([
                    "group_suggestions.{$index}.media_ids" => 'A photo may appear only once in a group.',
                ]);
            }

            AssistantOutput::ensureKnownIds(
                $mediaIds,
                $allowedMediaIds,
                "group_suggestions.{$index}.media_ids",
            );

            if (array_intersect($groupedMediaIds, $mediaIds) !== []) {
                throw ValidationException::withMessages([
                    "group_suggestions.{$index}.media_ids" => 'Suggested photo groups must not overlap.',
                ]);
            }

            $groupedMediaIds = [...$groupedMediaIds, ...$mediaIds];
            $payloads[] = new AuthoringArtifactPayload(
                type: AuthoringArtifactType::ImageGroupSuggestion,
                parentId: $planId,
                position: $index + 1,
                payload: [
                    'media_ids' => $mediaIds,
                    'section_heading' => AssistantOutput::stringIn(
                        $group,
                        'section_heading',
                        "group_suggestions.{$index}.section_heading",
                    ),
                    'sequence_rationale' => AssistantOutput::stringIn(
                        $group,
                        'sequence_rationale',
                        "group_suggestions.{$index}.sequence_rationale",
                    ),
                ],
            );
        }

        return $payloads;
    }

    private function isRequiredMedia(AuthoringTask $task, int $mediaId): bool
    {
        $media = $task->context['media'] ?? [];

        if (! is_array($media)) {
            return false;
        }

        return array_any(
            $media,
            static fn (mixed $item): bool => is_array($item)
                && ($item['id'] ?? null) === $mediaId
                && ($item['story_priority'] ?? null) === 'required',
        );
    }
}

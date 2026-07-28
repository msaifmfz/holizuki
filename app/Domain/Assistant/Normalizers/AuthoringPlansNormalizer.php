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

final readonly class AuthoringPlansNormalizer implements AuthoringOutputNormalizer
{
    /** @return list<AuthoringArtifactPayload> */
    public function normalize(AuthoringTask $task, Post $post, AssistantOutput $output): array
    {
        $validated = Validator::make($output->all(), [
            'plans' => ['required', 'array', 'size:3'],
            'plans.*.title' => ['required', 'string', 'max:255'],
            'plans.*.angle' => ['required', 'string', 'max:1000'],
            'plans.*.sections' => ['required', 'array', 'min:2', 'max:20'],
            'plans.*.sections.*' => ['required', 'string', 'max:255'],
            'plans.*.estimated_length' => ['required', Rule::in(['short', 'medium', 'long'])],
            'plans.*.source_ids' => ['present', 'array', 'max:50'],
            'plans.*.source_ids.*' => ['integer'],
        ])->validate();

        $plans = AssistantOutput::objectList($validated['plans'] ?? null, 'plans');
        $allowedSourceIds = AssistantOutput::integerList($task->context['source_ids'] ?? []);
        $payloads = [];

        foreach ($plans as $index => $plan) {
            $sourceIds = AssistantOutput::strictIntegerList(
                $plan['source_ids'] ?? null,
                "plans.{$index}.source_ids",
            );

            // Within one plan, not across them: separate plans may legitimately
            // draw on the same source.
            if (count($sourceIds) !== count(array_unique($sourceIds))) {
                throw ValidationException::withMessages([
                    "plans.{$index}.source_ids" => 'A plan may reference each source only once.',
                ]);
            }

            AssistantOutput::ensureKnownIds(
                $sourceIds,
                $allowedSourceIds,
                "plans.{$index}.source_ids",
            );
            $sections = AssistantOutput::stringList(
                $plan['sections'] ?? null,
                "plans.{$index}.sections",
            );

            if (count($sections) !== count(array_unique($sections))) {
                throw ValidationException::withMessages([
                    "plans.{$index}.sections" => 'Plan section headings must be unique.',
                ]);
            }

            $payloads[] = new AuthoringArtifactPayload(
                type: AuthoringArtifactType::Plan,
                parentId: null,
                position: $index + 1,
                payload: [
                    'title' => AssistantOutput::stringIn($plan, 'title', "plans.{$index}.title"),
                    'angle' => AssistantOutput::stringIn($plan, 'angle', "plans.{$index}.angle"),
                    'sections' => $sections,
                    'estimated_length' => AssistantOutput::stringIn(
                        $plan,
                        'estimated_length',
                        "plans.{$index}.estimated_length",
                    ),
                    'source_ids' => $sourceIds,
                ],
            );
        }

        return $payloads;
    }
}

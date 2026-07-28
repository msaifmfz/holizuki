<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Normalizers;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\ValidPhotoPassageMarkdown;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Assistant\ValueObjects\AuthoringArtifactPayload;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuthoringImagePassagesNormalizer implements AuthoringOutputNormalizer
{
    /** @return list<AuthoringArtifactPayload> */
    public function normalize(AuthoringTask $task, Post $post, AssistantOutput $output): array
    {
        $validated = Validator::make($output->all(), [
            'passages' => ['required', 'array', 'size:3'],
            'passages.*.label' => ['required', 'string', 'max:120', 'distinct'],
            'passages.*.body_markdown' => [
                'required',
                'string',
                'max:12000',
                new ValidPhotoPassageMarkdown,
            ],
        ])->validate();
        $planId = $task->context['plan_artifact_id'] ?? null;
        $compositionId = $task->context['composition_id'] ?? null;

        if (! is_int($planId) || ! is_int($compositionId)) {
            throw ValidationException::withMessages([
                'composition' => 'The image passage task no longer has a valid composition.',
            ]);
        }

        $composition = AuthoringImageComposition::query()
            ->whereKey($compositionId)
            ->whereBelongsTo($post)
            ->where('plan_id', $planId)
            ->where('usage', AuthoringImageCompositionUsage::Include)
            ->first();

        if (! $composition instanceof AuthoringImageComposition) {
            throw ValidationException::withMessages([
                'composition' => 'The selected image composition is no longer available.',
            ]);
        }

        $passages = AssistantOutput::objectList($validated['passages'] ?? null, 'passages');
        $payloads = [];
        $normalizedBodies = [];

        foreach ($passages as $index => $passage) {
            $bodyMarkdown = AssistantOutput::stringIn(
                $passage,
                'body_markdown',
                "passages.{$index}.body_markdown",
            );

            // Three options that differ only in whitespace or casing give the
            // author no real choice.
            $normalizedBody = Str::lower(Str::squish($bodyMarkdown));

            if (in_array($normalizedBody, $normalizedBodies, true)) {
                throw ValidationException::withMessages([
                    "passages.{$index}.body_markdown" => 'Each photo passage option must use genuinely different wording.',
                ]);
            }

            $normalizedBodies[] = $normalizedBody;
            $payloads[] = new AuthoringArtifactPayload(
                type: AuthoringArtifactType::ImagePassage,
                parentId: $planId,
                position: $index + 1,
                payload: [
                    'composition_id' => $composition->public_id,
                    'label' => AssistantOutput::stringIn(
                        $passage,
                        'label',
                        "passages.{$index}.label",
                    ),
                    'body_markdown' => $bodyMarkdown,
                ],
            );
        }

        return $payloads;
    }
}

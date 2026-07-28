<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Normalizers;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Assistant\ValueObjects\AuthoringArtifactPayload;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Validator;

final readonly class AuthoringResearchNormalizer implements AuthoringOutputNormalizer
{
    /** @return list<AuthoringArtifactPayload> */
    public function normalize(AuthoringTask $task, Post $post, AssistantOutput $output): array
    {
        $validated = Validator::make($output->all(), [
            'findings' => ['required', 'array', 'min:1', 'max:20'],
            'findings.*.title' => ['required', 'string', 'max:500'],
            'findings.*.summary' => ['required', 'string', 'max:5000'],
            'findings.*.url' => ['required', 'url:http,https', 'max:2048', 'distinct'],
            'findings.*.publisher' => ['required', 'string', 'max:255'],
            'findings.*.published_at' => ['present', 'nullable', 'date_format:Y-m-d'],
            'findings.*.suggested_citation' => ['required', 'string', 'max:500'],
        ])->validate();

        $findings = AssistantOutput::objectList($validated['findings'] ?? null, 'findings');
        $payloads = [];

        foreach ($findings as $index => $finding) {
            $payloads[] = new AuthoringArtifactPayload(
                type: AuthoringArtifactType::ResearchFinding,
                parentId: null,
                position: $index + 1,
                payload: [
                    'title' => AssistantOutput::stringIn($finding, 'title', "findings.{$index}.title"),
                    'summary' => AssistantOutput::stringIn(
                        $finding,
                        'summary',
                        "findings.{$index}.summary",
                    ),
                    'url' => AssistantOutput::stringIn($finding, 'url', "findings.{$index}.url"),
                    'publisher' => AssistantOutput::stringIn(
                        $finding,
                        'publisher',
                        "findings.{$index}.publisher",
                    ),
                    'published_at' => AssistantOutput::nullableStringIn(
                        $finding,
                        'published_at',
                        "findings.{$index}.published_at",
                    ),
                    'suggested_citation' => AssistantOutput::stringIn(
                        $finding,
                        'suggested_citation',
                        "findings.{$index}.suggested_citation",
                    ),
                ],
            );
        }

        return $payloads;
    }
}

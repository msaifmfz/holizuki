<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Normalizers;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\DraftStructureValidator;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Assistant\ValueObjects\AuthoringArtifactPayload;
use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class AuthoringDraftNormalizer implements AuthoringOutputNormalizer
{
    public function __construct(
        private MarkdownParser $markdownParser,
        private WorkspaceManager $workspace,
        private DraftStructureValidator $structure,
    ) {}

    /** @return list<AuthoringArtifactPayload> */
    public function normalize(AuthoringTask $task, Post $post, AssistantOutput $output): array
    {
        $validated = Validator::make($output->all(), [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:500'],
            'body_markdown' => [
                'required',
                'string',
                'max:'.config()->integer('assistant.limits.draft_markdown_bytes', 262144),
            ],
            'citations' => ['present', 'array', 'max:50'],
            'citations.*.number' => ['required', 'integer', 'min:1', 'distinct'],
            'citations.*.source_id' => ['required', 'integer', 'distinct'],
            'citations.*.title' => ['required', 'string', 'max:500'],
            'citations.*.url' => ['present', 'nullable', 'url:http,https', 'max:2048'],
        ])->validate();

        $plan = $this->plan($task, $post);
        [$citations, $sourceIds] = $this->citations(
            AssistantOutput::objectList($validated['citations'] ?? null, 'citations'),
        );
        AssistantOutput::ensureKnownIds(
            $sourceIds,
            AssistantOutput::integerList($task->context['citable_source_ids'] ?? []),
            'citations',
        );

        $bodyMarkdown = $output->string('body_markdown');
        $document = $this->markdownParser->parse(
            $bodyMarkdown,
            $this->workspace->mediaMap($post),
        );
        Validator::make(
            ['body' => $document->toArray()],
            ['body' => [new ValidRichTextDocument]],
        )->validate();

        if ($document->referencedSourceIds() !== $sourceIds) {
            throw ValidationException::withMessages([
                'body_markdown' => 'Citation links in the article must match the citation list in first-appearance order.',
            ]);
        }

        $this->structure->validate($task, $plan, $bodyMarkdown, $document);

        return [new AuthoringArtifactPayload(
            type: AuthoringArtifactType::Draft,
            parentId: $plan->id,
            position: 1,
            payload: [
                'title' => $output->string('title'),
                'excerpt' => $output->string('excerpt'),
                'body_markdown' => $bodyMarkdown,
                'citations' => $citations,
            ],
        )];
    }

    private function plan(AuthoringTask $task, Post $post): AuthoringArtifact
    {
        $planId = $task->context['plan_artifact_id'] ?? null;

        if (! is_int($planId)) {
            throw ValidationException::withMessages([
                'plan' => 'The draft task no longer has a valid plan.',
            ]);
        }

        $plan = AuthoringArtifact::query()
            ->whereKey($planId)
            ->where('post_id', $post->id)
            ->where('type', AuthoringArtifactType::Plan)
            ->first();

        if (! $plan instanceof AuthoringArtifact) {
            throw ValidationException::withMessages([
                'plan' => 'The selected plan no longer exists.',
            ]);
        }

        return $plan;
    }

    /**
     * @param  list<array<string, mixed>>  $citations
     * @return array{list<array<string, mixed>>, list<int>}
     */
    private function citations(array $citations): array
    {
        $normalized = [];
        $numbers = [];
        $sourceIds = [];

        foreach ($citations as $index => $citation) {
            $number = AssistantOutput::integerIn($citation, 'number', "citations.{$index}.number");
            $sourceId = AssistantOutput::integerIn(
                $citation,
                'source_id',
                "citations.{$index}.source_id",
            );
            $numbers[] = $number;
            $sourceIds[] = $sourceId;
            $normalized[] = [
                'number' => $number,
                'source_id' => $sourceId,
                'title' => AssistantOutput::stringIn($citation, 'title', "citations.{$index}.title"),
                'url' => AssistantOutput::nullableStringIn(
                    $citation,
                    'url',
                    "citations.{$index}.url",
                ),
            ];
        }

        if ($numbers !== ($numbers === [] ? [] : range(1, count($numbers)))) {
            throw ValidationException::withMessages([
                'citations' => 'Citation numbers must be consecutive and ordered from 1.',
            ]);
        }

        return [$normalized, $sourceIds];
    }
}

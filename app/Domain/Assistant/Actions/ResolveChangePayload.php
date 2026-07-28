<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Exceptions\StaleChangeException;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Turns an accepted proposal into a SavePostDraft payload, re-resolved against
 * the post's *current* state — never the state the proposal was computed
 * from. If the touched region no longer exists the change is stale and the
 * author is asked to re-run the assistant instead of silently clobbering.
 */
class ResolveChangePayload
{
    public function __construct(
        private readonly WorkspaceManager $workspace,
        private readonly MarkdownParser $parser,
    ) {}

    /** @return array<string, mixed> */
    public function handle(AssistantChange $change, Post $post): array
    {
        $payload = $change->payload;
        $draft = $post->draft()->firstOrFail();

        if ($change->type === AssistantChangeType::Body) {
            return [
                'body' => $this->resolveBody($post, $payload),
                'lock_version' => $post->lock_version,
            ];
        }

        if ($change->type === AssistantChangeType::Tags) {
            $old = $this->validatedTags($payload['old'] ?? null);
            $new = $this->validatedTags($payload['new'] ?? null);
            $current = $draft->tags()->pluck('name')->all();

            if ($this->canonicalTags($old) !== $this->canonicalTags($current)) {
                throw StaleChangeException::becauseContentMoved();
            }

            return ['tags' => $new, 'lock_version' => $post->lock_version];
        }

        $attribute = $change->type->postAttribute();

        if ($attribute === null) {
            throw StaleChangeException::becauseContentMoved();
        }

        $current = $draft->getAttribute($attribute);
        $old = $payload['old'] ?? null;

        // Empty string and null both mean "unset" across the meta.json boundary.
        if (($current === '' ? null : $current) !== ($old === '' ? null : $old)) {
            throw StaleChangeException::becauseContentMoved();
        }

        $new = $payload['new'] ?? null;

        if (! $this->scalarValueIsValid($change->type, $new)) {
            throw StaleChangeException::becauseContentMoved();
        }

        return [$attribute => $new, 'lock_version' => $post->lock_version];
    }

    /**
     * Splice the hunk into the current body's markdown and parse the result
     * back to a validated document.
     *
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function resolveBody(Post $post, array $payload): array
    {
        $currentMarkdown = $this->workspace->draftMarkdown($post);
        $oldBlocks = is_string($payload['old_blocks'] ?? null) ? $payload['old_blocks'] : '';
        $newBlocks = is_string($payload['new_blocks'] ?? null) ? $payload['new_blocks'] : '';
        $anchorBefore = is_string($payload['anchor_before'] ?? null) ? $payload['anchor_before'] : null;
        $anchorAfter = is_string($payload['anchor_after'] ?? null) ? $payload['anchor_after'] : null;
        $spliced = $this->spliceExact($currentMarkdown, $oldBlocks, $newBlocks, $anchorBefore, $anchorAfter);

        $document = $this->parser->parse($spliced, $this->workspace->mediaMap($post));

        $valid = Validator::make(
            ['body' => $document->toArray()],
            ['body' => [new ValidRichTextDocument]],
        )->passes();

        if (! $valid) {
            throw StaleChangeException::becauseContentMoved();
        }

        return $document->toArray();
    }

    private function spliceExact(
        string $markdown,
        string $oldBlocks,
        string $newBlocks,
        ?string $anchorBefore,
        ?string $anchorAfter,
    ): string {
        $markdown = rtrim($markdown, "\n");

        if ($oldBlocks === '') {
            return $this->insertExact($markdown, $newBlocks, $anchorBefore, $anchorAfter);
        }

        $target = $this->blockSequence($anchorBefore, $oldBlocks, $anchorAfter);
        $replacement = $this->blockSequence(
            $anchorBefore,
            $newBlocks === '' ? null : $newBlocks,
            $anchorAfter,
        );

        if (! $this->targetIsUnambiguous($markdown, $target, $anchorBefore, $anchorAfter)) {
            throw StaleChangeException::becauseContentMoved();
        }

        return $this->withTerminalNewline($this->replaceFirst($markdown, $target, $replacement));
    }

    private function insertExact(
        string $markdown,
        string $newBlocks,
        ?string $anchorBefore,
        ?string $anchorAfter,
    ): string {
        if ($newBlocks === '') {
            throw StaleChangeException::becauseContentMoved();
        }

        if ($anchorBefore === null && $anchorAfter === null) {
            if ($markdown !== '') {
                throw StaleChangeException::becauseContentMoved();
            }

            return $this->withTerminalNewline($newBlocks);
        }

        $target = $this->blockSequence($anchorBefore, null, $anchorAfter);
        $replacement = $this->blockSequence($anchorBefore, $newBlocks, $anchorAfter);

        if (! $this->targetIsUnambiguous($markdown, $target, $anchorBefore, $anchorAfter)) {
            throw StaleChangeException::becauseContentMoved();
        }

        return $this->withTerminalNewline($this->replaceFirst($markdown, $target, $replacement));
    }

    private function targetIsUnambiguous(
        string $markdown,
        string $target,
        ?string $anchorBefore,
        ?string $anchorAfter,
    ): bool {
        if ($target === '' || substr_count($markdown, $target) !== 1) {
            return false;
        }

        if ($anchorBefore === null && ! str_starts_with($markdown, $target)) {
            return false;
        }

        return $anchorAfter !== null || str_ends_with($markdown, $target);
    }

    private function blockSequence(?string ...$blocks): string
    {
        return implode("\n\n", array_values(array_filter(
            $blocks,
            static fn (?string $block): bool => $block !== null && $block !== '',
        )));
    }

    private function withTerminalNewline(string $markdown): string
    {
        return $markdown === '' ? '' : $markdown."\n";
    }

    /**
     * @return list<string>
     */
    private function validatedTags(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw StaleChangeException::becauseContentMoved();
        }

        $tags = [];

        foreach ($value as $tag) {
            if (! is_string($tag)) {
                throw StaleChangeException::becauseContentMoved();
            }

            $tags[] = trim($tag);
        }

        if (Validator::make(
            ['tags' => $tags],
            [
                'tags' => ['array', 'max:10'],
                'tags.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            ],
        )->fails()) {
            throw StaleChangeException::becauseContentMoved();
        }

        return $tags;
    }

    /**
     * @param  array<mixed>  $tags
     * @return list<string>
     */
    private function canonicalTags(array $tags): array
    {
        $canonical = array_map(
            static fn (mixed $tag): string => Str::lower(trim(is_string($tag) ? $tag : '')),
            $tags,
        );
        sort($canonical);

        return $canonical;
    }

    private function scalarValueIsValid(AssistantChangeType $type, mixed $value): bool
    {
        $maximum = match ($type) {
            AssistantChangeType::Excerpt,
            AssistantChangeType::OgDescription,
            AssistantChangeType::FeaturedImageCaption => 500,
            AssistantChangeType::Title,
            AssistantChangeType::SeoTitle,
            AssistantChangeType::MetaDescription,
            AssistantChangeType::OgTitle,
            AssistantChangeType::FeaturedImageAlt => 255,
            default => null,
        };

        return $maximum !== null && Validator::make(
            ['value' => $value],
            ['value' => ['nullable', 'string', 'max:'.$maximum]],
        )->passes();
    }

    private function replaceFirst(string $subject, string $search, string $replace): string
    {
        $position = strpos($subject, $search);

        if ($position === false) {
            throw StaleChangeException::becauseContentMoved();
        }

        return substr($subject, 0, $position).$replace.substr($subject, $position + strlen($search));
    }
}

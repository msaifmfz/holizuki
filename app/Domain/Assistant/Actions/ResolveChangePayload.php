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

/**
 * Turns an accepted proposal into a SavePost payload, re-resolved against
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

        if ($change->type === AssistantChangeType::Body) {
            return [
                'body' => $this->resolveBody($post, $payload),
                'lock_version' => $post->lock_version,
            ];
        }

        if ($change->type === AssistantChangeType::Tags) {
            $new = is_array($payload['new'] ?? null) ? array_values(array_filter($payload['new'], is_string(...))) : [];

            return ['tags' => $new, 'lock_version' => $post->lock_version];
        }

        $attribute = $change->type->postAttribute();

        if ($attribute === null) {
            throw StaleChangeException::becauseContentMoved();
        }

        $current = $post->getAttribute($attribute);
        $old = $payload['old'] ?? null;

        // Empty string and null both mean "unset" across the meta.json boundary.
        if (($current === '' ? null : $current) !== ($old === '' ? null : $old)) {
            throw StaleChangeException::becauseContentMoved();
        }

        $new = $payload['new'] ?? null;

        return [$attribute => is_string($new) ? $new : null, 'lock_version' => $post->lock_version];
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

        $spliced = $oldBlocks === ''
            ? $this->insert($currentMarkdown, $newBlocks, $anchorBefore)
            : $this->replace($currentMarkdown, $oldBlocks, $newBlocks, $anchorBefore);

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

    private function insert(string $markdown, string $newBlocks, ?string $anchorBefore): string
    {
        if ($newBlocks === '') {
            throw StaleChangeException::becauseContentMoved();
        }

        if ($anchorBefore === null || ! str_contains($markdown, $anchorBefore)) {
            return rtrim($markdown) === ''
                ? $newBlocks."\n"
                : rtrim($markdown, "\n")."\n\n".$newBlocks."\n";
        }

        $position = strpos($markdown, $anchorBefore);

        if ($position === false) {
            throw StaleChangeException::becauseContentMoved();
        }

        $insertAt = $position + strlen($anchorBefore);

        return substr($markdown, 0, $insertAt)."\n\n".$newBlocks.substr($markdown, $insertAt);
    }

    private function replace(string $markdown, string $oldBlocks, string $newBlocks, ?string $anchorBefore): string
    {
        $occurrences = substr_count($markdown, $oldBlocks);

        if ($occurrences === 0) {
            throw StaleChangeException::becauseContentMoved();
        }

        if ($occurrences === 1) {
            return $this->replaceFirst($markdown, $oldBlocks, $newBlocks);
        }

        if ($anchorBefore !== null) {
            $anchored = $anchorBefore."\n\n".$oldBlocks;

            if (substr_count($markdown, $anchored) === 1) {
                return $this->replaceFirst($markdown, $anchored, $anchorBefore."\n\n".$newBlocks);
            }
        }

        throw StaleChangeException::becauseContentMoved();
    }

    private function replaceFirst(string $subject, string $search, string $replace): string
    {
        $position = strpos($subject, $search);

        if ($position === false) {
            throw StaleChangeException::becauseContentMoved();
        }

        $result = substr($subject, 0, $position).$replace.substr($subject, $position + strlen($search));

        // A deletion can leave doubled blank lines behind; collapse them so
        // the spliced markdown stays in the serializer's normal form.
        return (string) preg_replace("/\n{3,}/", "\n\n", $result);
    }
}

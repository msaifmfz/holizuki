<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Illuminate\Validation\ValidationException;

/**
 * Checks that a generated draft honours what the author already approved: the
 * selected plan's section headings, the exact set of story photos chosen in the
 * photo flow, and each approved passage sitting immediately before its photos
 * in the section it was written for.
 *
 * The agent is free to write the prose around them; it is not free to move,
 * reword, duplicate, or drop the parts the author signed off on.
 */
final readonly class DraftStructureValidator
{
    private const string SECTION_HEADING_PATTERN = '/^##\h+(.+?)\h*$/mu';

    public function validate(
        AuthoringTask $task,
        AuthoringArtifact $plan,
        string $bodyMarkdown,
        RichTextDocument $document,
    ): void {
        $sections = AssistantOutput::stringList($plan->payload['sections'] ?? null, 'plan.sections');
        $sectionRanges = $this->sectionRanges($bodyMarkdown);

        if (array_keys($sectionRanges) !== $sections) {
            throw ValidationException::withMessages([
                'body_markdown' => 'The draft must preserve every selected-plan section heading in order.',
            ]);
        }

        $includedMediaIds = AssistantOutput::integerList($task->context['included_media_ids'] ?? []);
        $this->assertUsesExactlyApprovedPhotos($bodyMarkdown, $document, $includedMediaIds);

        $photoFlow = AssistantOutput::objectList($task->context['photo_flow'] ?? [], 'photo_flow');
        $this->assertApprovedPassagesSurviveIntact($bodyMarkdown, $photoFlow);
        $this->assertPhotosFollowTheirPassages($bodyMarkdown, $document, $photoFlow, $sectionRanges);
    }

    /**
     * @param  list<int>  $includedMediaIds
     */
    private function assertUsesExactlyApprovedPhotos(
        string $bodyMarkdown,
        RichTextDocument $document,
        array $includedMediaIds,
    ): void {
        $referencedMediaIds = $document->referencedMediaIds();
        $expectedMediaIds = $includedMediaIds;
        sort($expectedMediaIds);
        sort($referencedMediaIds);

        if ($referencedMediaIds !== $expectedMediaIds) {
            throw ValidationException::withMessages([
                'body_markdown' => 'The draft must use exactly the story photos approved in the photo flow.',
            ]);
        }

        foreach ($includedMediaIds as $mediaId) {
            if (count($this->mediaTokenSpans($bodyMarkdown, $mediaId)) !== 1) {
                throw ValidationException::withMessages([
                    'body_markdown' => "Story photo {$mediaId} must appear exactly once.",
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $photoFlow
     */
    private function assertApprovedPassagesSurviveIntact(string $bodyMarkdown, array $photoFlow): void
    {
        $expectedPassages = [];

        foreach ($photoFlow as $composition) {
            if (! $this->isIncluded($composition)) {
                continue;
            }

            $passage = $composition['passage_markdown'] ?? null;

            if (! is_string($passage) || trim($passage) === '') {
                throw ValidationException::withMessages([
                    'body_markdown' => 'Every included photo composition must have an approved passage.',
                ]);
            }

            $passageKey = hash('sha256', $passage);
            $expectedPassages[$passageKey] ??= ['markdown' => $passage, 'count' => 0];
            $expectedPassages[$passageKey]['count']++;
        }

        foreach ($expectedPassages as $expectedPassage) {
            if (
                count($this->passageBlockSpans($bodyMarkdown, $expectedPassage['markdown']))
                !== $expectedPassage['count']
            ) {
                throw ValidationException::withMessages([
                    'body_markdown' => 'The draft changed, duplicated, or omitted an author-approved photo passage.',
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $photoFlow
     * @param  array<string, array{int, int}>  $sectionRanges
     */
    private function assertPhotosFollowTheirPassages(
        string $bodyMarkdown,
        RichTextDocument $document,
        array $photoFlow,
        array $sectionRanges,
    ): void {
        $imageMetadata = $this->imageMetadataByMediaId($document);
        $sectionCursors = [];

        foreach ($photoFlow as $index => $composition) {
            if (! $this->isIncluded($composition)) {
                continue;
            }

            $sectionHeading = AssistantOutput::stringIn(
                $composition,
                'section_heading',
                "photo_flow.{$index}.section_heading",
            );
            $range = $sectionRanges[$sectionHeading] ?? null;

            if (! is_array($range)) {
                throw ValidationException::withMessages([
                    'body_markdown' => "The approved photo section “{$sectionHeading}” is missing.",
                ]);
            }

            $compositionMedia = AssistantOutput::objectList(
                $composition['media'] ?? null,
                "photo_flow.{$index}.media",
            );
            $passage = AssistantOutput::stringIn(
                $composition,
                'passage_markdown',
                "photo_flow.{$index}.passage_markdown",
            );
            // Compositions are walked in author order, so each one must start
            // after the previous composition's photos in the same section.
            $searchOffset = $sectionCursors[$sectionHeading] ?? $range[0];
            $passageSpan = array_find(
                $this->passageBlockSpans($bodyMarkdown, $passage),
                static fn (array $span): bool => $span['start'] >= $searchOffset
                    && $span['start'] >= $range[0]
                    && $span['end'] <= $range[1],
            );

            if (! is_array($passageSpan)) {
                throw ValidationException::withMessages([
                    'body_markdown' => 'The draft changed or moved an author-approved photo passage.',
                ]);
            }

            $sectionCursors[$sectionHeading] = $this->assertGroupFollows(
                $bodyMarkdown,
                $compositionMedia,
                $imageMetadata,
                $range,
                $passageSpan['end'],
                $index,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $compositionMedia
     * @param  array<int, array{alt_text: string, caption: string|null}>  $imageMetadata
     * @param  array{int, int}  $range
     * @return int The offset just past the last photo in the group.
     */
    private function assertGroupFollows(
        string $bodyMarkdown,
        array $compositionMedia,
        array $imageMetadata,
        array $range,
        int $blockCursor,
        int $compositionIndex,
    ): int {
        foreach ($compositionMedia as $mediaIndex => $media) {
            $mediaId = AssistantOutput::integerIn(
                $media,
                'id',
                "photo_flow.{$compositionIndex}.media.{$mediaIndex}.id",
            );
            $span = $this->mediaTokenSpans($bodyMarkdown, $mediaId)[0] ?? null;
            $separator = is_array($span) && $span['start'] >= $blockCursor
                ? substr($bodyMarkdown, $blockCursor, $span['start'] - $blockCursor)
                : null;
            // The first photo starts a new block after the passage; the rest of
            // a group must sit flush against each other.
            $hasValidSeparator = is_string($separator)
                && ($mediaIndex === 0
                    ? $this->isBlankLineSeparator($separator)
                    : trim($separator) === '');

            if (
                ! is_array($span)
                || $span['start'] < $range[0]
                || $span['end'] > $range[1]
                || $span['start'] < $blockCursor
                || ! $hasValidSeparator
            ) {
                throw ValidationException::withMessages([
                    'body_markdown' => "Story photo {$mediaId} must immediately follow its approved passage and group sequence.",
                ]);
            }

            $expectedAltText = AssistantOutput::nullableStringIn(
                $media,
                'alt_text',
                "photo_flow.{$compositionIndex}.media.{$mediaIndex}.alt_text",
            ) ?? '';
            $expectedCaption = AssistantOutput::nullableStringIn(
                $media,
                'caption',
                "photo_flow.{$compositionIndex}.media.{$mediaIndex}.caption",
            );
            $expectedCaption = $expectedCaption === '' ? null : $expectedCaption;
            $actualMetadata = $imageMetadata[$mediaId] ?? null;

            if (
                ! is_array($actualMetadata)
                || $actualMetadata['alt_text'] !== $expectedAltText
                || $actualMetadata['caption'] !== $expectedCaption
            ) {
                throw ValidationException::withMessages([
                    'body_markdown' => "Story photo {$mediaId} must use its approved alt text and caption exactly.",
                ]);
            }

            $blockCursor = $span['end'];
        }

        return $blockCursor;
    }

    /** @param array<string, mixed> $composition */
    private function isIncluded(array $composition): bool
    {
        return ($composition['usage'] ?? null) === AuthoringImageCompositionUsage::Include->value;
    }

    /**
     * Section headings mapped to the byte range they own, heading through to
     * the next heading or end of document.
     *
     * @return array<string, array{int, int}>
     */
    private function sectionRanges(string $markdown): array
    {
        preg_match_all(
            self::SECTION_HEADING_PATTERN,
            $markdown,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        $ranges = [];

        foreach ($matches as $index => $match) {
            $heading = trim($match[1][0]);
            $start = $match[0][1];
            $end = isset($matches[$index + 1])
                ? $matches[$index + 1][0][1]
                : strlen($markdown);
            $ranges[$heading] = [$start, $end];
        }

        return $ranges;
    }

    /** @return list<array{start: int, end: int}> */
    private function passageBlockSpans(string $markdown, string $passage): array
    {
        $spans = [];
        $offset = 0;
        $passageLength = strlen($passage);
        $markdownLength = strlen($markdown);

        while (($start = strpos($markdown, $passage, $offset)) !== false) {
            $end = $start + $passageLength;

            // Only count occurrences that stand alone as their own block, so a
            // passage quoted inside a larger paragraph does not satisfy it.
            if (
                $this->hasBlankLineBoundaryBefore($markdown, $start)
                && ($end === $markdownLength || $this->hasBlankLineBoundaryAfter($markdown, $end))
            ) {
                $spans[] = ['start' => $start, 'end' => $end];
            }

            $offset = $start + 1;
        }

        return $spans;
    }

    private function hasBlankLineBoundaryBefore(string $markdown, int $offset): bool
    {
        if ($offset === 0) {
            return true;
        }

        // The boundary is made only of line breaks and horizontal whitespace,
        // so the run of those characters immediately before the offset is the
        // whole of what could match. Scanning it rather than the entire prefix
        // keeps repeated occurrence checks off an O(document) path.
        $start = $offset;

        while ($start > 0 && $this->isBoundaryCharacter($markdown[$start - 1])) {
            $start--;
        }

        return preg_match(
            '/(?:\r\n|\n|\r)[\t ]*(?:\r\n|\n|\r)\z/',
            substr($markdown, $start, $offset - $start),
        ) === 1;
    }

    private function hasBlankLineBoundaryAfter(string $markdown, int $offset): bool
    {
        $end = $offset;
        $length = strlen($markdown);

        while ($end < $length && $this->isBoundaryCharacter($markdown[$end])) {
            $end++;
        }

        return preg_match(
            '/\A(?:\r\n|\n|\r)[\t ]*(?:\r\n|\n|\r)/',
            substr($markdown, $offset, $end - $offset),
        ) === 1;
    }

    private function isBoundaryCharacter(string $character): bool
    {
        return in_array($character, ["\n", "\r", "\t", ' '], true);
    }

    private function isBlankLineSeparator(string $separator): bool
    {
        return trim($separator) === ''
            && preg_match('/(?:\r\n|\n|\r)[\t ]*(?:\r\n|\n|\r)/', $separator) === 1;
    }

    /** @return list<array{start: int, end: int}> */
    private function mediaTokenSpans(string $markdown, int $mediaId): array
    {
        preg_match_all(
            '/!\[(?:\\\\.|[^\]\\\\\r\n])*\]\(media:'.preg_quote((string) $mediaId, '/').'(?:\h+"(?:\\\\.|[^"\\\\\r\n])*")?\)/u',
            $markdown,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        return array_map(
            static fn (array $match): array => [
                'start' => $match[1],
                'end' => $match[1] + strlen($match[0]),
            ],
            $matches[0],
        );
    }

    /** @return array<int, array{alt_text: string, caption: string|null}> */
    private function imageMetadataByMediaId(RichTextDocument $document): array
    {
        $metadata = [];
        $nodes = [$document->toArray()];

        while ($nodes !== []) {
            $node = array_pop($nodes);
            $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $mediaId = $attributes['mediaId'] ?? null;

            if (($node['type'] ?? null) === 'image' && is_int($mediaId)) {
                $metadata[$mediaId] = [
                    'alt_text' => is_string($attributes['alt'] ?? null) ? $attributes['alt'] : '',
                    'caption' => is_string($attributes['caption'] ?? null)
                        ? $attributes['caption']
                        : null,
                ];
            }

            $content = $node['content'] ?? null;

            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $child) {
                if (is_array($child)) {
                    $nodes[] = $child;
                }
            }
        }

        return $metadata;
    }
}

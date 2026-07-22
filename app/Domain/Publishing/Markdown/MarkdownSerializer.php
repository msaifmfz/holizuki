<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Markdown;

use App\Domain\Publishing\ValueObjects\RichTextDocument;

/**
 * Renders a RichTextDocument as markdown in the codec dialect. The output is
 * the AI-editable rendition of a post body; MarkdownParser reverses it. The
 * serializer is total over documents accepted by ValidRichTextDocument and
 * the pair is a fixpoint: serialize(parse(serialize($doc))) === serialize($doc).
 */
final class MarkdownSerializer
{
    public function serialize(RichTextDocument $document): string
    {
        $content = $document->toArray()['content'] ?? [];
        $blocks = is_array($content) ? $this->renderBlocks($content) : [];

        return $blocks === [] ? '' : implode("\n\n", $blocks)."\n";
    }

    /**
     * @param  array<mixed>  $nodes
     * @return list<string>
     */
    private function renderBlocks(array $nodes): array
    {
        $blocks = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $rendered = $this->renderBlock($node);

            if ($rendered !== null) {
                $blocks[] = $rendered;
            }
        }

        return $blocks;
    }

    /** @param array<mixed> $node */
    private function renderBlock(array $node): ?string
    {
        return match ($node['type'] ?? null) {
            'paragraph' => $this->renderParagraph($node),
            'heading' => $this->renderHeading($node),
            'bulletList' => $this->renderList($node, ordered: false),
            'orderedList' => $this->renderList($node, ordered: true),
            'blockquote' => $this->renderBlockquote($node),
            'codeBlock' => $this->renderCodeBlock($node),
            'horizontalRule' => '---',
            'image' => $this->renderImage($node),
            default => null,
        };
    }

    /** @param array<mixed> $node */
    private function renderParagraph(array $node): ?string
    {
        $inline = trim($this->renderInline($this->children($node)), ' ');

        return $inline === '' ? null : $this->escapeLineStarts($inline);
    }

    /** @param array<mixed> $node */
    private function renderHeading(array $node): ?string
    {
        $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $level = is_int($attributes['level'] ?? null) ? $attributes['level'] : 2;
        $inline = $this->renderInline($this->children($node));

        return trim($inline) === '' ? null : str_repeat('#', $level).' '.$inline;
    }

    /** @param array<mixed> $node */
    private function renderList(array $node, bool $ordered): ?string
    {
        $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $start = is_int($attributes['start'] ?? null) ? $attributes['start'] : 1;
        $items = [];
        $ordinal = $start;

        foreach ($this->children($node) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['type'] ?? null) !== 'listItem') {
                continue;
            }
            $marker = $ordered ? $ordinal.'. ' : '- ';
            $items[] = $this->renderListItem($item, $marker);
            $ordinal++;
        }

        return $items === [] ? null : implode("\n", $items);
    }

    /** @param array<mixed> $item */
    private function renderListItem(array $item, string $marker): string
    {
        $blocks = $this->renderBlocks($this->children($item));

        if ($blocks === []) {
            return rtrim($marker);
        }

        $body = implode("\n\n", $blocks);
        $indent = str_repeat(' ', strlen($marker));
        $lines = explode("\n", $body);
        $rendered = $marker.array_shift($lines);

        foreach ($lines as $line) {
            $rendered .= "\n".($line === '' ? '' : $indent.$line);
        }

        return $rendered;
    }

    /** @param array<mixed> $node */
    private function renderBlockquote(array $node): ?string
    {
        $blocks = $this->renderBlocks($this->children($node));

        if ($blocks === []) {
            return null;
        }

        $lines = explode("\n", implode("\n\n", $blocks));

        return implode("\n", array_map(
            fn (string $line): string => $line === '' ? '>' : '> '.$line,
            $lines,
        ));
    }

    /** @param array<mixed> $node */
    private function renderCodeBlock(array $node): string
    {
        $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $language = $attributes['language'] ?? null;
        $language = is_string($language) && RichTextDocument::supportsCodeLanguage($language) ? $language : 'plaintext';

        $code = '';

        foreach ($this->children($node) as $child) {
            if (is_array($child) && is_string($child['text'] ?? null)) {
                $code .= $child['text'];
            }
        }

        preg_match_all('/`+/', $code, $matches);
        $longestRun = array_reduce($matches[0], fn (int $carry, string $run): int => max($carry, strlen($run)), 0);
        $fence = str_repeat('`', max(3, $longestRun + 1));

        return $fence.$language."\n".($code === '' ? '' : rtrim($code, "\n")."\n").$fence;
    }

    /** @param array<mixed> $node */
    private function renderImage(array $node): ?string
    {
        $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $mediaId = $attributes['mediaId'] ?? null;

        if (! is_int($mediaId) || $mediaId <= 0) {
            return null;
        }

        $alt = is_string($attributes['alt'] ?? null) ? $attributes['alt'] : '';
        $caption = is_string($attributes['caption'] ?? null) ? $attributes['caption'] : null;
        $title = $caption === null || $caption === ''
            ? ''
            : ' "'.addcslashes($caption, '"\\').'"';

        return '!['.$this->escapeText($alt).']('.MarkdownDialect::mediaUrl($mediaId).$title.')';
    }

    /** @param array<mixed> $nodes */
    private function renderInline(array $nodes): string
    {
        $rendered = '';
        $index = 0;
        $count = count($nodes);

        while ($index < $count) {
            $node = $nodes[$index];

            if (! is_array($node)) {
                $index++;

                continue;
            }

            $type = $node['type'] ?? null;

            if ($type === 'hardBreak') {
                $rendered = rtrim($rendered, ' ')."\\\n";
                $index++;

                continue;
            }

            if ($type !== 'text') {
                $index++;

                continue;
            }

            $marks = $this->marksOf($node);

            if ($marks === []) {
                $rendered .= $this->escapeText($this->textOf($node));
                $index++;

                continue;
            }

            $outermost = $marks[0];
            $group = [];

            while ($index < $count) {
                $candidate = $nodes[$index];

                if (! is_array($candidate) || ($candidate['type'] ?? null) !== 'text') {
                    break;
                }

                $candidateMarks = $this->marksOf($candidate);

                if ($candidateMarks === [] || ! $this->sameMark($candidateMarks[0], $outermost)) {
                    break;
                }

                $inner = $candidate;
                $inner['marks'] = array_slice($candidateMarks, 1);
                $group[] = $inner;
                $index++;
            }

            $rendered .= $this->renderMarked($outermost, $group);
        }

        return $rendered;
    }

    /**
     * @param  array<mixed>  $mark
     * @param  list<array<mixed>>  $nodes
     */
    private function renderMarked(array $mark, array $nodes): string
    {
        if (($mark['type'] ?? null) === 'code') {
            return $this->renderCodeSpan($nodes);
        }

        $inner = $this->renderInline($nodes);

        [$leading, $core, $trailing] = $this->splitBoundaryWhitespace($inner);

        if ($core === '') {
            return $inner;
        }

        return $leading.match ($mark['type'] ?? null) {
            'bold' => '**'.$core.'**',
            'italic' => '*'.$core.'*',
            'strike' => '~~'.$core.'~~',
            'underline' => '<u>'.$core.'</u>',
            'link' => '['.$core.']('.$this->escapeLinkDestination($this->linkHref($mark)).')',
            default => $core,
        }.$trailing;
    }

    /** @param list<array<mixed>> $nodes */
    private function renderCodeSpan(array $nodes): string
    {
        $code = '';

        foreach ($nodes as $node) {
            $code .= $this->textOf($node);
        }

        preg_match_all('/`+/', $code, $matches);
        $longestRun = array_reduce($matches[0], fn (int $carry, string $run): int => max($carry, strlen($run)), 0);
        $delimiter = str_repeat('`', $longestRun + 1);
        $padding = $longestRun > 0 || str_starts_with($code, ' ') || str_ends_with($code, ' ') || str_starts_with($code, '`') || str_ends_with($code, '`')
            ? ' '
            : '';

        return $delimiter.$padding.$code.$padding.$delimiter;
    }

    /**
     * @param  array<mixed>  $node
     * @return list<array<mixed>>
     */
    private function marksOf(array $node): array
    {
        $marks = [];

        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            if (is_array($mark) && is_string($mark['type'] ?? null)) {
                $marks[] = $mark;
            }
        }

        return MarkdownDialect::sortMarks($marks);
    }

    /**
     * @param  array<mixed>  $left
     * @param  array<mixed>  $right
     */
    private function sameMark(array $left, array $right): bool
    {
        if (($left['type'] ?? null) !== ($right['type'] ?? null)) {
            return false;
        }

        if (($left['type'] ?? null) === 'link') {
            return $this->linkHref($left) === $this->linkHref($right);
        }

        return true;
    }

    /** @param array<mixed> $mark */
    private function linkHref(array $mark): string
    {
        $attributes = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];

        return is_string($attributes['href'] ?? null) ? $attributes['href'] : '';
    }

    /** @param array<mixed> $node */
    private function textOf(array $node): string
    {
        $text = $node['text'] ?? null;

        return is_string($text) ? $text : '';
    }

    /** @return array{string, string, string} */
    private function splitBoundaryWhitespace(string $text): array
    {
        $core = ltrim($text, " \n");
        $leading = substr($text, 0, strlen($text) - strlen($core));
        $trimmed = rtrim($core, " \n");
        $trailing = substr($core, strlen($trimmed));

        return [$leading, $trimmed, $trailing];
    }

    /**
     * Backslash-escape characters that would otherwise be parsed as markdown
     * syntax. `<` and `>` are included so literal angle brackets survive the
     * dialect's `<u>` underline convention.
     */
    private function escapeText(string $text): string
    {
        return addcslashes($text, '\\`*_[]<>&~');
    }

    /**
     * Escape characters that only act as syntax at the start of a line:
     * headings, blockquotes, list markers, and setext underlines.
     */
    private function escapeLineStarts(string $block): string
    {
        return implode("\n", array_map(
            function (string $line): string {
                if (preg_match('/^([#+\-=])/', $line) === 1) {
                    return '\\'.$line;
                }

                return (string) preg_replace('/^(\d{1,9})([.)])/', '$1\\\\$2', $line);
            },
            explode("\n", $block),
        ));
    }

    private function escapeLinkDestination(string $url): string
    {
        return addcslashes($url, '()\\');
    }

    /**
     * @param  array<mixed>  $node
     * @return array<mixed>
     */
    private function children(array $node): array
    {
        return is_array($node['content'] ?? null) ? $node['content'] : [];
    }
}

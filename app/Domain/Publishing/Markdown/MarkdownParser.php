<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Markdown;

use App\Domain\Publishing\ValueObjects\RichTextDocument;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser as CommonMarkParser;

/**
 * Parses codec-dialect markdown back into a RichTextDocument constrained to
 * the ValidRichTextDocument whitelist. Unsupported constructs degrade rather
 * than fail: unknown HTML is stripped, heading levels clamp to 2-3, images
 * without a resolvable `media:` reference are dropped.
 */
final readonly class MarkdownParser
{
    private CommonMarkParser $parser;

    public function __construct()
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new StrikethroughExtension);

        $this->parser = new CommonMarkParser($environment);
    }

    /**
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $media
     *                                                                                    Attributes of the post's media rows, keyed by media id. Image
     *                                                                                    references outside this map are dropped from the document.
     */
    public function parse(string $markdown, array $media = []): RichTextDocument
    {
        $blocks = $this->parseBlocks($this->parser->parse($markdown), $media);

        return RichTextDocument::fromArray(['type' => 'doc', 'content' => $blocks]);
    }

    /**
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $media
     * @return list<array<string, mixed>>
     */
    private function parseBlocks(Node $parent, array $media): array
    {
        $blocks = [];

        foreach ($parent->children() as $child) {
            foreach ($this->parseBlock($child, $media) as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $media
     * @return list<array<string, mixed>>
     */
    private function parseBlock(Node $node, array $media): array
    {
        return match (true) {
            $node instanceof Paragraph => $this->parseParagraph($node, $media),
            $node instanceof Heading => $this->parseHeading($node),
            $node instanceof ListBlock => $this->parseList($node, $media),
            $node instanceof BlockQuote => $this->wrap('blockquote', $this->parseBlocks($node, $media)),
            $node instanceof FencedCode => [$this->codeBlock($node->getLiteral(), $node->getInfoWords()[0] ?? null)],
            $node instanceof IndentedCode => [$this->codeBlock($node->getLiteral(), null)],
            $node instanceof ThematicBreak => [['type' => 'horizontalRule']],
            default => [],
        };
    }

    /**
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $media
     * @return list<array<string, mixed>>
     */
    private function parseParagraph(Paragraph $paragraph, array $media): array
    {
        $blocks = [];
        $inline = [];
        $underline = false;

        $flush = function () use (&$blocks, &$inline): void {
            $content = $this->finishInline($inline);
            $inline = [];

            if ($content !== []) {
                $blocks[] = ['type' => 'paragraph', 'content' => $content];
            }
        };

        foreach ($paragraph->children() as $child) {
            if ($child instanceof Image) {
                $image = $this->imageNode($child, $media);

                if ($image !== null) {
                    $flush();
                    $blocks[] = $image;
                }

                continue;
            }

            foreach ($this->parseInline($child, [], $underline) as $node) {
                $inline[] = $node;
            }
        }

        $flush();

        return $blocks;
    }

    /** @return list<array<string, mixed>> */
    private function parseHeading(Heading $heading): array
    {
        $underline = false;
        $content = [];

        foreach ($heading->children() as $child) {
            foreach ($this->parseInline($child, [], $underline) as $node) {
                $content[] = $node;
            }
        }

        $content = $this->finishInline($content);

        if ($content === []) {
            return [];
        }

        return [[
            'type' => 'heading',
            'attrs' => ['level' => min(3, max(2, $heading->getLevel()))],
            'content' => $content,
        ]];
    }

    /**
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $media
     * @return list<array<string, mixed>>
     */
    private function parseList(ListBlock $list, array $media): array
    {
        $items = [];

        foreach ($list->children() as $child) {
            if (! $child instanceof ListItem) {
                continue;
            }

            $itemBlocks = $this->parseBlocks($child, $media);
            $items[] = ['type' => 'listItem', 'content' => $itemBlocks === [] ? [['type' => 'paragraph']] : $itemBlocks];
        }

        if ($items === []) {
            return [];
        }

        $ordered = $list->getListData()->type === ListBlock::TYPE_ORDERED;

        $node = [
            'type' => $ordered ? 'orderedList' : 'bulletList',
            'content' => $items,
        ];

        if ($ordered) {
            $node = ['type' => 'orderedList', 'attrs' => ['start' => $list->getListData()->start ?? 1], 'content' => $items];
        }

        return [$node];
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function parseInline(Node $node, array $marks, bool &$underline): array
    {
        if ($node instanceof Text) {
            return $this->textNodes($node->getLiteral(), $marks, $underline);
        }

        if ($node instanceof Code) {
            return $this->textNodes($node->getLiteral(), [...$marks, ['type' => 'code']], $underline);
        }

        if ($node instanceof Newline) {
            return $node->getType() === Newline::HARDBREAK
                ? [['type' => 'hardBreak']]
                : $this->textNodes(' ', $marks, $underline);
        }

        if ($node instanceof HtmlInline) {
            $tag = strtolower(trim($node->getLiteral()));

            if ($tag === '<u>') {
                $underline = true;
            } elseif ($tag === '</u>') {
                $underline = false;
            }

            return [];
        }

        $childMarks = match (true) {
            $node instanceof Strong => [...$marks, ['type' => 'bold']],
            $node instanceof Emphasis => [...$marks, ['type' => 'italic']],
            $node instanceof Strikethrough => [...$marks, ['type' => 'strike']],
            $node instanceof Link => [...$marks, ['type' => 'link', 'attrs' => ['href' => $node->getUrl()]]],
            default => $marks,
        };

        $nodes = [];

        foreach ($node->children() as $child) {
            foreach ($this->parseInline($child, $childMarks, $underline) as $inline) {
                $nodes[] = $inline;
            }
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function textNodes(string $text, array $marks, bool $underline): array
    {
        if ($text === '') {
            return [];
        }

        if ($underline) {
            $marks = [...$marks, ['type' => 'underline']];
        }

        $node = ['type' => 'text', 'text' => $text];

        if ($marks !== []) {
            $node['marks'] = MarkdownDialect::sortMarks($marks);
        }

        return [$node];
    }

    /**
     * Merge adjacent text nodes carrying identical marks and trim boundary
     * whitespace so the result is byte-stable across another serialize pass.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function finishInline(array $nodes): array
    {
        $merged = [];

        foreach ($nodes as $node) {
            $previous = $merged === [] ? null : $merged[count($merged) - 1];

            if ($previous !== null
                && ($node['type'] ?? null) === 'text'
                && ($previous['type'] ?? null) === 'text'
                && ($node['marks'] ?? []) === ($previous['marks'] ?? [])) {
                $merged[count($merged) - 1]['text'] = $this->textOf($previous).$this->textOf($node);

                continue;
            }

            $merged[] = $node;
        }

        return array_values(array_filter(
            $merged,
            fn (array $node): bool => ($node['type'] ?? null) !== 'text'
                || trim($this->textOf($node)) !== ''
                || count($merged) > 1,
        ));
    }

    /** @param array<string, mixed> $node */
    private function textOf(array $node): string
    {
        $text = $node['text'] ?? null;

        return is_string($text) ? $text : '';
    }

    /** @return array<string, mixed> */
    private function codeBlock(string $literal, ?string $info): array
    {
        $language = RichTextDocument::supportsCodeLanguage($info) ? $info : 'plaintext';
        $code = rtrim($literal, "\n");

        $node = ['type' => 'codeBlock', 'attrs' => ['language' => $language]];

        if ($code !== '') {
            $node['content'] = [['type' => 'text', 'text' => $code]];
        }

        return $node;
    }

    /**
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $media
     * @return array<string, mixed>|null
     */
    private function imageNode(Image $image, array $media): ?array
    {
        $mediaId = MarkdownDialect::mediaIdFromUrl($image->getUrl());

        if ($mediaId === null || ! isset($media[$mediaId])) {
            return null;
        }

        $alt = '';
        $underline = false;

        foreach ($image->children() as $child) {
            foreach ($this->parseInline($child, [], $underline) as $node) {
                if (($node['type'] ?? null) === 'text') {
                    $alt .= $this->textOf($node);
                }
            }
        }

        $title = $image->getTitle();

        return [
            'type' => 'image',
            'attrs' => [
                'mediaId' => $mediaId,
                'src' => $media[$mediaId]['src'],
                'alt' => $alt,
                'caption' => $title === null || $title === '' ? null : $title,
                'width' => $media[$mediaId]['width'],
                'height' => $media[$mediaId]['height'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function wrap(string $type, array $blocks): array
    {
        return $blocks === [] ? [] : [['type' => $type, 'content' => $blocks]];
    }
}

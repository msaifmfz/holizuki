<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

final readonly class ValidPhotoPassageMarkdown implements ValidationRule
{
    private MarkdownParser $parser;

    public function __construct()
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new StrikethroughExtension);

        $this->parser = new MarkdownParser($environment);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isValid($value)) {
            $fail('Photo passages must contain one to three paragraphs using text and safe inline formatting only.');
        }
    }

    private function isValid(string $markdown): bool
    {
        if ($this->containsUnescapedImageMarker($markdown)) {
            return false;
        }

        $document = $this->parser->parse($markdown);
        $paragraphCount = 0;

        foreach ($document->children() as $block) {
            if (! $block instanceof Paragraph) {
                return false;
            }

            $paragraphCount++;

            if (
                $paragraphCount > 3
                || ! $this->validParagraph($block)
                || ! $this->hasReadableText($block)
            ) {
                return false;
            }
        }

        return $paragraphCount > 0;
    }

    private function validParagraph(Paragraph $paragraph): bool
    {
        $underlineOpen = false;

        foreach ($paragraph->children() as $inline) {
            if (! $this->validInline($inline, $underlineOpen)) {
                return false;
            }
        }

        return ! $underlineOpen;
    }

    private function validInline(Node $node, bool &$underlineOpen): bool
    {
        if ($node instanceof Text || $node instanceof Code || $node instanceof Newline) {
            return true;
        }

        if ($node instanceof HtmlInline) {
            return $this->validUnderlineTag($node, $underlineOpen);
        }

        if ($node instanceof Link && ! $this->validLink($node->getUrl())) {
            return false;
        }

        if (
            ! $node instanceof Link
            && ! $node instanceof Strong
            && ! $node instanceof Emphasis
            && ! $node instanceof Strikethrough
        ) {
            return false;
        }

        foreach ($node->children() as $child) {
            if (! $this->validInline($child, $underlineOpen)) {
                return false;
            }
        }

        return true;
    }

    private function validUnderlineTag(HtmlInline $html, bool &$underlineOpen): bool
    {
        $tag = Str::lower(trim($html->getLiteral()));

        if ($tag === '<u>') {
            if ($underlineOpen) {
                return false;
            }

            $underlineOpen = true;

            return true;
        }

        if ($tag !== '</u>' || ! $underlineOpen) {
            return false;
        }

        $underlineOpen = false;

        return true;
    }

    private function validLink(string $url): bool
    {
        return Str::length($url) <= 2048
            && Str::startsWith(Str::lower($url), ['https://', 'http://', 'mailto:']);
    }

    private function hasReadableText(Node $node): bool
    {
        if (
            ($node instanceof Text || $node instanceof Code)
            && Str::squish($node->getLiteral()) !== ''
        ) {
            return true;
        }

        foreach ($node->children() as $child) {
            if ($this->hasReadableText($child)) {
                return true;
            }
        }

        return false;
    }

    private function containsUnescapedImageMarker(string $markdown): bool
    {
        $offset = 0;

        while (($imageOffset = strpos($markdown, '![', $offset)) !== false) {
            $precedingBackslashes = 0;

            for ($index = $imageOffset - 1; $index >= 0 && $markdown[$index] === '\\'; $index--) {
                $precedingBackslashes++;
            }

            if ($precedingBackslashes % 2 === 0) {
                return true;
            }

            $offset = $imageOffset + 2;
        }

        return false;
    }
}

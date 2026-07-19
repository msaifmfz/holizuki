<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Rules;

use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ValidRichTextDocument implements ValidationRule
{
    /** @var list<string> */
    private const array ALLOWED_NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem',
        'blockquote', 'codeBlock', 'hardBreak', 'horizontalRule', 'image',
    ];

    /** @var list<string> */
    private const array ALLOWED_MARKS = ['bold', 'italic', 'underline', 'strike', 'code', 'link'];

    public function __construct(private readonly bool $requireContent = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null && ! $this->requireContent) {
            return;
        }

        if (! is_array($value) || ($value['type'] ?? null) !== 'doc' || ! $this->validNode($value, 0)) {
            $fail('The post body contains unsupported rich-text content.');

            return;
        }

        if (Str::length((string) json_encode($value)) > 1_000_000) {
            $fail('The post body may not be larger than 1 MB.');

            return;
        }

        if ($this->requireContent && RichTextDocument::fromArray($value)->plainText() === '') {
            $fail('The post body must contain text before publishing.');
        }
    }

    /** @param array<mixed> $node */
    private function validNode(array $node, int $depth): bool
    {
        $type = $node['type'] ?? null;

        if ($depth > 30 || ! is_string($type) || ! in_array($type, self::ALLOWED_NODES, true)) {
            return false;
        }

        if ($type === 'text') {
            $text = $node['text'] ?? null;

            if (! is_string($text) || $text === '') {
                return false;
            }
        }

        if ($type === 'heading') {
            $attributes = $node['attrs'] ?? null;

            if (! is_array($attributes)
                || array_keys($attributes) !== ['level']
                || ! in_array($attributes['level'] ?? null, [2, 3], true)) {
                return false;
            }
        }

        if ($type === 'codeBlock' && ! $this->validCodeBlockAttributes($node['attrs'] ?? null)) {
            return false;
        }

        if ($type === 'image' && ! $this->validImageAttributes($node['attrs'] ?? null)) {
            return false;
        }

        $marks = $node['marks'] ?? [];

        if (! is_array($marks)) {
            return false;
        }

        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                return false;
            }

            $markType = $mark['type'] ?? null;

            if (! is_string($markType) || ! in_array($markType, self::ALLOWED_MARKS, true)) {
                return false;
            }

            if ($markType === 'link') {
                $attributes = $mark['attrs'] ?? null;

                if (! is_array($attributes)
                    || array_diff(array_keys($attributes), ['href', 'target', 'rel', 'class']) !== []
                    || ! $this->validLink($attributes['href'] ?? null)) {
                    return false;
                }
            }
        }

        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return false;
        }

        return array_all($content, fn (mixed $child): bool => is_array($child) && $this->validNode($child, $depth + 1));
    }

    private function validLink(mixed $href): bool
    {
        return is_string($href)
            && Str::length($href) <= 2048
            && Str::startsWith(Str::lower($href), ['https://', 'http://', 'mailto:']);
    }

    private function validCodeBlockAttributes(mixed $attributes): bool
    {
        if ($attributes === null) {
            return true;
        }

        if (! is_array($attributes) || array_diff(array_keys($attributes), ['language']) !== []) {
            return false;
        }

        $language = $attributes['language'] ?? null;

        return $language === null || RichTextDocument::supportsCodeLanguage($language);
    }

    private function validImageAttributes(mixed $attributes): bool
    {
        if (! is_array($attributes)
            || array_diff(array_keys($attributes), ['mediaId', 'src', 'alt', 'caption', 'width', 'height']) !== []) {
            return false;
        }

        $mediaId = $attributes['mediaId'] ?? null;
        $source = $attributes['src'] ?? null;
        $alternativeText = $attributes['alt'] ?? null;
        $caption = $attributes['caption'] ?? null;
        $width = $attributes['width'] ?? null;
        $height = $attributes['height'] ?? null;

        return is_int($mediaId)
            && $mediaId > 0
            && is_string($source)
            && Str::length($source) <= 2048
            && is_string($alternativeText)
            && trim($alternativeText) !== ''
            && Str::length($alternativeText) <= 255
            && ($caption === null || (is_string($caption) && Str::length($caption) <= 500))
            && ($width === null || (is_int($width) && $width > 0 && $width <= 10_000))
            && ($height === null || (is_int($height) && $height > 0 && $height <= 10_000));
    }
}

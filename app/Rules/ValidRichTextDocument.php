<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ValidRichTextDocument implements ValidationRule
{
    /** @var list<string> */
    private const array ALLOWED_NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem',
        'blockquote', 'codeBlock', 'hardBreak', 'horizontalRule',
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

        if ($this->requireContent && trim($this->plainText($value)) === '') {
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

            if (! is_array($attributes) || ! in_array($attributes['level'] ?? null, [2, 3], true)) {
                return false;
            }
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

                if (! is_array($attributes) || ! $this->validLink($attributes['href'] ?? null)) {
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
        return is_string($href) && Str::startsWith(Str::lower($href), ['https://', 'http://', 'mailto:']);
    }

    /** @param array<mixed> $node */
    private function plainText(array $node): string
    {
        $text = is_string($node['text'] ?? null) ? $node['text'] : '';
        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return $text;
        }

        foreach ($content as $child) {
            if (is_array($child)) {
                $text .= ' '.$this->plainText($child);
            }
        }

        return $text;
    }
}

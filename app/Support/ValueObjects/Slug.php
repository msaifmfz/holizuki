<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use Illuminate\Support\Str;
use Stringable;

/**
 * A normalized URL slug. Construction guarantees the value is URL-safe and
 * never empty; uniqueness against a table is the caller's concern.
 */
final readonly class Slug implements Stringable
{
    private function __construct(public string $value) {}

    public static function fromName(string $name, string $fallback = 'untitled'): self
    {
        $base = Str::slug($name);

        return new self($base === '' ? $fallback : $base);
    }

    public function withSuffix(int $suffix): self
    {
        return new self($this->value.'-'.$suffix);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

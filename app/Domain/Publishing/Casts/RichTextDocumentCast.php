<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Casts;

use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<RichTextDocument, RichTextDocument|array<mixed>|null>
 */
class RichTextDocumentCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RichTextDocument
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? RichTextDocument::fromArray($decoded) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $document = $value instanceof RichTextDocument ? $value->toArray() : $value;

        return json_encode($document, JSON_THROW_ON_ERROR);
    }
}

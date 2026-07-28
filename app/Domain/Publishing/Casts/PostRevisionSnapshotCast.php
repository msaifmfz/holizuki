<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Casts;

use App\Domain\Publishing\ValueObjects\PostRevisionSnapshot;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<PostRevisionSnapshot, PostRevisionSnapshot|array<mixed>>
 */
final class PostRevisionSnapshotCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): PostRevisionSnapshot
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('A post revision snapshot cannot be empty.');
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('A post revision snapshot must be a JSON object.');
        }

        return PostRevisionSnapshot::fromArray($decoded);
    }

    /**
     * Writes the snapshot together with the projections reference checks query.
     * Deriving them here rather than in a model event keeps them inseparable
     * from the snapshot they summarize, including under Event::fake().
     *
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $snapshot = $value instanceof PostRevisionSnapshot
            ? $value
            : (is_array($value) ? PostRevisionSnapshot::fromArray($value) : null);

        if (! $snapshot instanceof PostRevisionSnapshot) {
            throw new InvalidArgumentException('A post revision snapshot is required.');
        }

        return [
            $key => json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR),
            'referenced_paths' => json_encode($snapshot->referencedFilePaths(), JSON_THROW_ON_ERROR),
            'referenced_media_ids' => json_encode($snapshot->referencedMediaIds(), JSON_THROW_ON_ERROR),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Publishing\Models\Post;
use Closure;
use Throwable;

/**
 * Memoizes authoring context builds inside an explicitly marked read.
 *
 * Staleness checks compare a stored input hash against a freshly built context,
 * so listing artifacts rebuilds the same contexts once per artifact and once
 * per image composition — the dominant cost of the polled artifact index.
 *
 * Memoization is off by default and only applies inside `during()`. Contexts
 * depend on media metadata, sources, and photo compositions, none of which move
 * the post's lock version, so caching across a request that also writes could
 * hand back a context that no longer describes reality. Callers that write get
 * the uncached path; only pure reads opt in.
 */
final class AuthoringContextCache
{
    /** @var array<string, mixed> */
    private array $entries = [];

    private bool $enabled = false;

    /**
     * Memoize context builds for the duration of a read-only operation.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $read
     * @return TReturn
     */
    public function during(Closure $read): mixed
    {
        $wasEnabled = $this->enabled;
        $this->enabled = true;

        try {
            return $read();
        } finally {
            $this->enabled = $wasEnabled;

            if (! $wasEnabled) {
                $this->entries = [];
            }
        }
    }

    /**
     * @template TValue
     *
     * @param  list<string|int|null>  $keyParts
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(Post $post, string $name, array $keyParts, Closure $build): mixed
    {
        if (! $this->enabled) {
            return $build();
        }

        $key = implode(':', [$post->id, $post->lock_version, $name, ...array_map(
            static fn (string|int|null $part): string => (string) $part,
            $keyParts,
        )]);

        if (! array_key_exists($key, $this->entries)) {
            try {
                $this->entries[$key] = $build();
            } catch (Throwable $exception) {
                // Never memoize a failed build; the next call must retry it.
                unset($this->entries[$key]);

                throw $exception;
            }
        }

        /** @var TValue $value */
        $value = $this->entries[$key];

        return $value;
    }
}

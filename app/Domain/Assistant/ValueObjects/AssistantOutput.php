<?php

declare(strict_types=1);

namespace App\Domain\Assistant\ValueObjects;

use Illuminate\Validation\ValidationException;

/**
 * A decoded assistant JSON payload with typed readers.
 *
 * The agent's output is untrusted: it has passed shape validation, but nothing
 * guarantees a field holds the type the caller expects. Every reader here fails
 * loudly with the offending key rather than coercing, so a malformed field can
 * never reach a stored artifact as a silently wrong value.
 */
final readonly class AssistantOutput
{
    /** @param array<string, mixed> $fields */
    private function __construct(private array $fields) {}

    /** @param array<string, mixed> $fields */
    public static function fromArray(array $fields): self
    {
        return new self($fields);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->fields;
    }

    public function raw(string $field): mixed
    {
        return $this->fields[$field] ?? null;
    }

    public function string(string $field, ?string $key = null): string
    {
        return self::stringIn($this->fields, $field, $key ?? $field);
    }

    /** @param array<string, mixed> $value */
    public static function stringIn(array $value, string $field, string $key): string
    {
        $result = $value[$field] ?? null;

        if (! is_string($result)) {
            self::invalid($key);
        }

        return $result;
    }

    /** @param array<string, mixed> $value */
    public static function nullableStringIn(array $value, string $field, string $key): ?string
    {
        $result = $value[$field] ?? null;

        if ($result !== null && ! is_string($result)) {
            self::invalid($key);
        }

        return $result;
    }

    /** @param array<string, mixed> $value */
    public static function integerIn(array $value, string $field, string $key): int
    {
        $result = $value[$field] ?? null;

        if (! is_int($result)) {
            self::invalid($key);
        }

        return $result;
    }

    /**
     * Integers already known to be well formed, used for values this
     * application wrote into the task context rather than the agent's output.
     *
     * @return list<int>
     */
    public static function integerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_int(...)));
    }

    /** @return list<int> */
    public static function strictIntegerList(mixed $value, string $key): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            self::invalid($key);
        }

        $integers = [];

        foreach ($value as $item) {
            if (! is_int($item)) {
                self::invalid($key);
            }

            $integers[] = $item;
        }

        return $integers;
    }

    /** @return list<string> */
    public static function stringList(mixed $value, string $key): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            self::invalid($key);
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                self::invalid($key);
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /** @return list<array<string, mixed>> */
    public static function objectList(mixed $value, string $key): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            self::invalid($key);
        }

        $objects = [];

        foreach ($value as $item) {
            if (! is_array($item) || array_is_list($item)) {
                self::invalid($key);
            }

            $object = [];

            foreach ($item as $field => $fieldValue) {
                if (! is_string($field)) {
                    self::invalid($key);
                }

                $object[$field] = $fieldValue;
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $allowedIds
     */
    public static function ensureKnownIds(array $ids, array $allowedIds, string $key): void
    {
        if (array_diff($ids, $allowedIds) === []) {
            return;
        }

        throw ValidationException::withMessages([
            $key => 'The assistant referenced a source or image that is not approved for this post.',
        ]);
    }

    public static function invalid(string $key): never
    {
        throw ValidationException::withMessages([
            $key => 'The assistant output did not use the required JSON field type.',
        ]);
    }
}

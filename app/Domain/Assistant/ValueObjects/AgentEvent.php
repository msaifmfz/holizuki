<?php

declare(strict_types=1);

namespace App\Domain\Assistant\ValueObjects;

/**
 * A typed event from the agent's stream-json output. Only the fields
 * relevant to the given type are populated.
 */
final readonly class AgentEvent
{
    public const string TYPE_INIT = 'init';

    public const string TYPE_TEXT = 'text';

    public const string TYPE_TOOL_USE = 'tool_use';

    public const string TYPE_RESULT = 'result';

    public const string TYPE_ERROR = 'error';

    private function __construct(
        public string $type,
        public ?string $sessionId = null,
        public ?string $text = null,
        public ?string $tool = null,
        public ?string $target = null,
        public bool $isError = false,
        public ?float $costUsd = null,
        public ?int $durationMs = null,
    ) {}

    public static function init(string $sessionId): self
    {
        return new self(self::TYPE_INIT, sessionId: $sessionId);
    }

    public static function text(string $text): self
    {
        return new self(self::TYPE_TEXT, text: $text);
    }

    public static function toolUse(string $tool, ?string $target): self
    {
        return new self(self::TYPE_TOOL_USE, tool: $tool, target: $target);
    }

    public static function result(bool $isError, ?string $text, ?float $costUsd, ?int $durationMs): self
    {
        return new self(self::TYPE_RESULT, text: $text, isError: $isError, costUsd: $costUsd, durationMs: $durationMs);
    }

    public static function error(string $message): self
    {
        return new self(self::TYPE_ERROR, text: $message, isError: true);
    }
}

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

    public const string TYPE_RETRY = 'retry';

    public const string TYPE_RATE_LIMIT = 'rate_limit';

    public const string TYPE_HEARTBEAT = 'heartbeat';

    /**
     * @param  array<string, int|float|array<string, int|float>>|null  $usage
     */
    private function __construct(
        public string $type,
        public ?string $sessionId = null,
        public ?string $text = null,
        public ?string $tool = null,
        public ?string $target = null,
        public bool $isError = false,
        public ?float $costUsd = null,
        public ?int $durationMs = null,
        public ?string $code = null,
        public ?string $subtype = null,
        public ?string $retryAt = null,
        public ?string $stopReason = null,
        public ?array $usage = null,
        public ?int $attempt = null,
        public ?int $retryDelayMs = null,
        public ?int $exitCode = null,
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

    /**
     * @param  array<string, int|float|array<string, int|float>>|null  $usage
     */
    public static function result(
        bool $isError,
        ?string $text,
        ?float $costUsd,
        ?int $durationMs,
        ?string $code = null,
        ?string $subtype = null,
        ?string $retryAt = null,
        ?string $stopReason = null,
        ?array $usage = null,
    ): self {
        return new self(
            self::TYPE_RESULT,
            text: $text,
            isError: $isError,
            costUsd: $costUsd,
            durationMs: $durationMs,
            code: $code,
            subtype: $subtype,
            retryAt: $retryAt,
            stopReason: $stopReason,
            usage: $usage,
        );
    }

    public static function error(
        string $message,
        ?string $code = null,
        ?string $subtype = null,
        ?string $retryAt = null,
        ?int $exitCode = null,
    ): self {
        return new self(
            self::TYPE_ERROR,
            text: $message,
            isError: true,
            code: $code,
            subtype: $subtype,
            retryAt: $retryAt,
            exitCode: $exitCode,
        );
    }

    public static function retry(string $code, int $attempt, int $retryDelayMs): self
    {
        return new self(
            self::TYPE_RETRY,
            code: $code,
            subtype: 'api_retry',
            attempt: $attempt,
            retryDelayMs: $retryDelayMs,
        );
    }

    public static function rateLimit(string $status, ?string $retryAt): self
    {
        return new self(
            self::TYPE_RATE_LIMIT,
            isError: $status === 'rejected',
            code: 'rate_limit',
            subtype: $status,
            retryAt: $retryAt,
        );
    }

    public static function heartbeat(): self
    {
        return new self(self::TYPE_HEARTBEAT);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\ValueObjects;

/**
 * Everything one headless agent invocation needs. The runner turns this into
 * a `claude -p` command line; tests script it against a fake runner.
 */
final readonly class AgentRequest
{
    /**
     * @param  list<string>  $allowedTools
     * @param  list<string>  $disallowedTools
     */
    public function __construct(
        public string $prompt,
        public string $workingDirectory,
        public string $workspacePath,
        public string $model,
        public string $sessionId,
        public bool $resume,
        public string $systemPrompt,
        public array $allowedTools,
        public array $disallowedTools,
        public int $maxTurns,
        public int $timeout,
        public int $startupTimeout = 30,
        public int $idleTimeout = 120,
        public int $terminationGrace = 3,
        public int $heartbeatInterval = 10,
        public int $cancellationPollInterval = 1,
        public int $maxStdoutBytes = 16_777_216,
        public int $maxStderrBytes = 65_536,
        public int $maxLineBytes = 4_194_304,
        public int $maxEventTextBytes = 131_072,
        public int $maxNarrationBytes = 131_072,
        public bool $noSessionPersistence = false,
        public ?int $turnId = null,
        public ?string $runToken = null,
    ) {}
}

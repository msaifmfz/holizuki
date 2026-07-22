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
        public string $workspacePath,
        public string $model,
        public string $sessionId,
        public bool $resume,
        public string $systemPrompt,
        public array $allowedTools,
        public array $disallowedTools,
        public int $maxTurns,
        public int $timeout,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use Generator;

/**
 * Boundary to the AI agent process. The production binding shells out to the
 * Claude Code CLI; tests bind a scripted fake.
 */
interface AgentRunner
{
    /** @return Generator<int, AgentEvent> */
    public function stream(AgentRequest $request): Generator;
}

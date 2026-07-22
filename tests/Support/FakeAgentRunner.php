<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use Closure;
use Generator;

/**
 * Scripted stand-in for the Claude Code CLI. Steps are either events to
 * yield or closures to run mid-stream (e.g. mutating workspace files the
 * way a real agent turn would). A closure may return an event to yield.
 */
class FakeAgentRunner implements AgentRunner
{
    public ?AgentRequest $lastRequest = null;

    /** @param list<AgentEvent|Closure> $script */
    public function __construct(public array $script = []) {}

    /** @return Generator<int, AgentEvent> */
    public function stream(AgentRequest $request): Generator
    {
        $this->lastRequest = $request;

        foreach ($this->script as $step) {
            if ($step instanceof Closure) {
                $result = $step($request);

                if ($result instanceof AgentEvent) {
                    yield $result;
                }

                continue;
            }

            yield $step;
        }
    }
}

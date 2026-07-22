<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\ValueObjects\AgentEvent;

/**
 * Translates Claude Code `--output-format stream-json` NDJSON lines into
 * typed AgentEvents. Unknown event shapes are ignored so CLI upgrades that
 * add event types degrade gracefully instead of failing turns.
 */
class StreamJsonParser
{
    /** @return list<AgentEvent> */
    public function parse(string $line): array
    {
        $line = trim($line);

        if ($line === '') {
            return [];
        }

        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return [];
        }

        return match ($decoded['type'] ?? null) {
            'system' => $this->parseSystem($decoded),
            'assistant' => $this->parseAssistant($decoded),
            'result' => $this->parseResult($decoded),
            default => [],
        };
    }

    /**
     * @param  array<mixed>  $event
     * @return list<AgentEvent>
     */
    private function parseSystem(array $event): array
    {
        if (($event['subtype'] ?? null) !== 'init' || ! is_string($event['session_id'] ?? null)) {
            return [];
        }

        return [AgentEvent::init($event['session_id'])];
    }

    /**
     * @param  array<mixed>  $event
     * @return list<AgentEvent>
     */
    private function parseAssistant(array $event): array
    {
        $message = is_array($event['message'] ?? null) ? $event['message'] : [];
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];
        $events = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null) && $block['text'] !== '') {
                $events[] = AgentEvent::text($block['text']);
            }

            if (($block['type'] ?? null) === 'tool_use' && is_string($block['name'] ?? null)) {
                $input = is_array($block['input'] ?? null) ? $block['input'] : [];
                $target = is_string($input['file_path'] ?? null) ? basename($input['file_path']) : null;
                $events[] = AgentEvent::toolUse($block['name'], $target);
            }
        }

        return $events;
    }

    /**
     * @param  array<mixed>  $event
     * @return list<AgentEvent>
     */
    private function parseResult(array $event): array
    {
        $cost = $event['total_cost_usd'] ?? null;
        $duration = $event['duration_ms'] ?? null;
        $result = $event['result'] ?? null;

        return [AgentEvent::result(
            isError: ($event['is_error'] ?? false) === true,
            text: is_string($result) ? $result : null,
            costUsd: is_float($cost) || is_int($cost) ? (float) $cost : null,
            durationMs: is_int($duration) ? $duration : null,
        )];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use Carbon\CarbonImmutable;

/**
 * Translates Claude Code `--output-format stream-json` NDJSON lines into
 * typed events. Unknown additive event shapes are ignored, while terminal
 * result variants are normalized into stable, safe failure codes.
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

        $decoded = json_decode($line, true, 64);

        if (! is_array($decoded)) {
            return [];
        }

        return match ($decoded['type'] ?? null) {
            'system' => $this->parseSystem($decoded),
            'assistant' => $this->parseAssistant($decoded),
            'result' => $this->parseResult($decoded),
            'rate_limit_event' => $this->parseRateLimit($decoded),
            default => [],
        };
    }

    public function errorFromDiagnostic(string $diagnostic, ?int $exitCode = null): AgentEvent
    {
        $code = $this->diagnosticCode($diagnostic) ?? AgentFailureCode::ProcessFailed;

        return AgentEvent::error(
            message: $code->message(),
            code: $code->value,
            subtype: 'process',
            exitCode: $exitCode,
        );
    }

    /**
     * @param  array<mixed>  $event
     * @return list<AgentEvent>
     */
    private function parseSystem(array $event): array
    {
        $subtype = $event['subtype'] ?? null;

        if ($subtype === 'init' && is_string($event['session_id'] ?? null)) {
            return [AgentEvent::init($event['session_id'])];
        }

        if ($subtype !== 'api_retry') {
            return [];
        }

        $error = is_string($event['error'] ?? null) ? $event['error'] : AgentFailureCode::Unknown->value;
        $attempt = is_int($event['attempt'] ?? null) ? $event['attempt'] : 1;
        $delay = is_int($event['retry_delay_ms'] ?? null) ? $event['retry_delay_ms'] : 0;

        return [AgentEvent::retry(
            code: $this->providerCode($error)->value,
            attempt: max(1, $attempt),
            retryDelayMs: max(0, $delay),
        )];
    }

    /**
     * @param  array<mixed>  $event
     * @return list<AgentEvent>
     */
    private function parseAssistant(array $event): array
    {
        if (is_string($event['error'] ?? null)) {
            $code = $this->providerCode($event['error']);

            return [AgentEvent::error(
                message: $code->message(),
                code: $code->value,
                subtype: 'assistant',
            )];
        }

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
        $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : 'unknown';
        $isError = $subtype !== 'success' || ($event['is_error'] ?? false) === true;
        $result = $event['result'] ?? null;
        $code = $isError ? $this->resultFailureCode($subtype, $event) : null;

        return [AgentEvent::result(
            isError: $isError,
            text: $isError ? $code?->message() : (is_string($result) ? $result : null),
            costUsd: is_float($cost) || is_int($cost) ? (float) $cost : null,
            durationMs: is_int($duration) ? $duration : null,
            code: $code?->value,
            subtype: $subtype,
            stopReason: is_string($event['stop_reason'] ?? null) ? $event['stop_reason'] : null,
            usage: $this->sanitizeUsage($event['usage'] ?? null),
        )];
    }

    /**
     * @param  array<mixed>  $event
     * @return list<AgentEvent>
     */
    private function parseRateLimit(array $event): array
    {
        $information = is_array($event['rate_limit_info'] ?? null) ? $event['rate_limit_info'] : [];
        $status = is_string($information['status'] ?? null) ? $information['status'] : 'rejected';

        if (! in_array($status, ['allowed', 'allowed_warning', 'rejected'], true)) {
            return [];
        }

        return [AgentEvent::rateLimit(
            status: $status,
            retryAt: $this->retryAt($information['resetsAt'] ?? null),
        )];
    }

    /** @param array<mixed> $event */
    private function resultFailureCode(string $subtype, array $event): AgentFailureCode
    {
        $diagnostics = is_array($event['errors'] ?? null)
            ? implode("\n", array_filter($event['errors'], is_string(...)))
            : '';
        $classified = $this->diagnosticCode($diagnostics);

        if ($classified instanceof AgentFailureCode) {
            return $classified;
        }

        return match ($subtype) {
            'error_max_turns' => AgentFailureCode::MaxTurns,
            'error_max_budget_usd' => AgentFailureCode::MaxBudget,
            'error_max_structured_output_retries' => AgentFailureCode::StructuredOutput,
            'error_during_execution' => AgentFailureCode::Execution,
            default => AgentFailureCode::ProtocolError,
        };
    }

    private function providerCode(string $code): AgentFailureCode
    {
        return match ($code) {
            'authentication_failed' => AgentFailureCode::AuthenticationFailed,
            'oauth_org_not_allowed' => AgentFailureCode::OAuthOrganizationNotAllowed,
            'billing_error' => AgentFailureCode::BillingError,
            'rate_limit' => AgentFailureCode::RateLimit,
            'overloaded' => AgentFailureCode::ProviderOverloaded,
            'invalid_request' => AgentFailureCode::InvalidRequest,
            'model_not_found' => AgentFailureCode::ModelNotFound,
            'server_error' => AgentFailureCode::ServerError,
            'max_output_tokens' => AgentFailureCode::MaxOutputTokens,
            default => AgentFailureCode::Unknown,
        };
    }

    private function diagnosticCode(string $diagnostic): ?AgentFailureCode
    {
        $diagnostic = mb_strtolower($diagnostic);

        return match (true) {
            $diagnostic === '' => null,
            str_contains($diagnostic, 'no conversation found'),
            str_contains($diagnostic, 'conversation could not be found') => AgentFailureCode::SessionNotFound,
            str_contains($diagnostic, 'not logged in'),
            str_contains($diagnostic, 'authentication'),
            str_contains($diagnostic, 'oauth') => AgentFailureCode::AuthenticationFailed,
            str_contains($diagnostic, 'session limit'),
            str_contains($diagnostic, 'weekly limit'),
            str_contains($diagnostic, 'usage limit'),
            str_contains($diagnostic, 'rate limit'),
            str_contains($diagnostic, 'too many requests') => AgentFailureCode::RateLimit,
            str_contains($diagnostic, 'billing'),
            str_contains($diagnostic, 'payment required') => AgentFailureCode::BillingError,
            str_contains($diagnostic, 'model not found'),
            str_contains($diagnostic, 'model is not available') => AgentFailureCode::ModelNotFound,
            default => null,
        };
    }

    private function retryAt(mixed $timestamp): ?string
    {
        if (! is_int($timestamp) && ! is_float($timestamp)) {
            return null;
        }

        $seconds = $timestamp > 10_000_000_000 ? $timestamp / 1000 : $timestamp;

        if ($seconds <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC($seconds)->toIso8601String();
    }

    /**
     * @return array<string, int|float|array<string, int|float>>|null
     */
    private function sanitizeUsage(mixed $usage): ?array
    {
        if (! is_array($usage)) {
            return null;
        }

        $sanitized = [];

        foreach (array_slice($usage, 0, 30, true) as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $sanitized[$key] = $value;

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $nested = [];

            foreach (array_slice($value, 0, 20, true) as $nestedKey => $nestedValue) {
                if (is_string($nestedKey) && (is_int($nestedValue) || is_float($nestedValue))) {
                    $nested[$nestedKey] = $nestedValue;
                }
            }

            if ($nested !== []) {
                $sanitized[$key] = $nested;
            }
        }

        return $sanitized === [] ? null : $sanitized;
    }
}

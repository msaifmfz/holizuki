<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Streams a locked-down Claude Code invocation without exposing the parent
 * Laravel process environment. Exactly one terminal result or error is
 * yielded after the child exits.
 */
class ClaudeCliRunner implements AgentRunner
{
    public function __construct(
        private readonly StreamJsonParser $parser,
        private readonly ClaudeProcessEnvironment $environment,
        private readonly AssistantAvailability $availability,
    ) {}

    /** @return Generator<int, AgentEvent> */
    public function stream(AgentRequest $request): Generator
    {
        if ($this->environment->oauthToken() === null) {
            $failure = $this->failure(AgentFailureCode::AuthenticationFailed);
            $this->availability->record($failure);

            yield $failure;

            return;
        }

        $workspaceFailure = $this->workspaceFailure($request);

        if ($workspaceFailure instanceof AgentEvent) {
            yield $workspaceFailure;

            return;
        }

        $process = new Process(
            command: $this->command($request),
            cwd: $request->workingDirectory,
            env: $this->environment->variables(),
            input: $request->prompt,
            timeout: $request->timeout,
        );
        $process->setIdleTimeout($request->idleTimeout);

        try {
            $process->start();
        } catch (Throwable $exception) {
            $this->logFailure(AgentFailureCode::ProcessStart, null, null, $exception);
            $failure = $this->failure(AgentFailureCode::ProcessStart);
            $this->availability->record($failure);

            yield $failure;

            return;
        }

        $buffer = '';
        $stderrTail = '';
        $stdoutBytes = 0;
        $narrationBytes = 0;
        $resultCount = 0;
        $result = null;
        $providerFailure = null;
        $terminalFailure = null;
        $initialized = false;
        $startedAt = microtime(true);
        $nextHeartbeatAt = $startedAt + max(1, $request->heartbeatInterval);
        $nextCancellationCheckAt = $startedAt + max(1, $request->cancellationPollInterval);

        try {
            while (true) {
                $running = $process->isRunning();

                if ($running) {
                    $process->checkTimeout();
                }

                $read = $this->readEvents(
                    $process,
                    $request,
                    $buffer,
                    $stderrTail,
                    $stdoutBytes,
                    final: ! $running,
                );

                if ($read['failure'] !== null) {
                    $terminalFailure = $read['failure'];
                }

                foreach ($read['events'] as $event) {
                    if ($event->type === AgentEvent::TYPE_INIT) {
                        $initialized = true;
                    }

                    if ($event->type === AgentEvent::TYPE_RESULT) {
                        $resultCount++;
                        $result = $event;

                        if ($resultCount > 1) {
                            $terminalFailure = $this->failure(AgentFailureCode::ProtocolError);
                        }

                        continue;
                    }

                    if ($event->type === AgentEvent::TYPE_ERROR) {
                        $providerFailure = $event;

                        continue;
                    }

                    if ($event->type === AgentEvent::TYPE_RETRY && $event->code !== null) {
                        $retryCode = AgentFailureCode::tryFrom($event->code) ?? AgentFailureCode::Unknown;
                        $providerFailure = $this->failure($retryCode);
                    }

                    if ($event->type === AgentEvent::TYPE_RATE_LIMIT && $event->isError) {
                        $providerFailure = AgentEvent::error(
                            message: AgentFailureCode::RateLimit->message(),
                            code: AgentFailureCode::RateLimit->value,
                            subtype: 'rate_limit_event',
                            retryAt: $event->retryAt,
                        );
                    }

                    if ($event->type === AgentEvent::TYPE_TEXT) {
                        $eventBytes = strlen((string) $event->text);
                        $narrationBytes += $eventBytes;

                        if (
                            $eventBytes > $request->maxEventTextBytes
                            || $narrationBytes > $request->maxNarrationBytes
                        ) {
                            $terminalFailure = $this->failure(AgentFailureCode::OutputLimit);

                            break;
                        }
                    }

                    $this->availability->record($event);

                    yield $event;
                }

                if ($terminalFailure !== null) {
                    break;
                }

                if (! $running) {
                    break;
                }

                $now = microtime(true);

                if (! $initialized && $now - $startedAt >= $request->startupTimeout) {
                    $terminalFailure = $this->failure(AgentFailureCode::StartupTimeout);

                    break;
                }

                if ($now >= $nextCancellationCheckAt) {
                    $nextCancellationCheckAt = $now + max(1, $request->cancellationPollInterval);

                    if ($this->isCancellationRequested($request) || connection_aborted() === 1) {
                        $terminalFailure = $this->failure(AgentFailureCode::Cancelled);

                        break;
                    }
                }

                if ($now >= $nextHeartbeatAt) {
                    $nextHeartbeatAt = $now + max(1, $request->heartbeatInterval);

                    yield AgentEvent::heartbeat();
                }

                Sleep::usleep(config()->integer('assistant.process.poll_microseconds', 10_000));
            }
        } catch (ProcessTimedOutException $exception) {
            $code = $exception->isIdleTimeout()
                ? AgentFailureCode::IdleTimeout
                : AgentFailureCode::TurnTimeout;
            $terminalFailure = $this->failure($code);
        } finally {
            if ($process->isRunning()) {
                $this->stop($process, $request->terminationGrace);
            }
        }

        if ($terminalFailure !== null) {
            $this->availability->record($terminalFailure);
            $this->logFailure(
                AgentFailureCode::tryFrom((string) $terminalFailure->code) ?? AgentFailureCode::Unknown,
                $process->getExitCode(),
                $stderrTail,
            );

            yield $terminalFailure;

            return;
        }

        $exitCode = $process->getExitCode();

        if ($resultCount !== 1 || ! $result instanceof AgentEvent) {
            $failure = $exitCode === 0
                ? $this->failure(AgentFailureCode::ProtocolError)
                : $this->parser->errorFromDiagnostic($stderrTail, $exitCode);
            $this->availability->record($failure);
            $this->logFailure(
                AgentFailureCode::tryFrom((string) $failure->code) ?? AgentFailureCode::Unknown,
                $exitCode,
                $stderrTail,
            );

            yield $failure;

            return;
        }

        if ($result->isError) {
            $result = $this->resultWithProviderFailure($result, $providerFailure);
            $this->availability->record($result);
            $this->logFailure(
                AgentFailureCode::tryFrom((string) $result->code) ?? AgentFailureCode::Unknown,
                $exitCode,
                $stderrTail,
                subtype: $result->subtype,
            );

            yield $result;

            return;
        }

        if ($exitCode !== 0) {
            $failure = $this->parser->errorFromDiagnostic($stderrTail, $exitCode);
            $this->availability->record($failure);
            $this->logFailure(
                AgentFailureCode::tryFrom((string) $failure->code) ?? AgentFailureCode::ProcessFailed,
                $exitCode,
                $stderrTail,
            );

            yield $failure;

            return;
        }

        yield $result;
    }

    /**
     * @return array{events: list<AgentEvent>, failure: AgentEvent|null}
     */
    private function readEvents(
        Process $process,
        AgentRequest $request,
        string &$buffer,
        string &$stderrTail,
        int &$stdoutBytes,
        bool $final,
    ): array {
        $stdout = $process->getIncrementalOutput();
        $stderr = $process->getIncrementalErrorOutput();
        $process->clearOutput();
        $process->clearErrorOutput();
        $events = [];

        if ($stderr !== '') {
            $stderrTail = substr($stderrTail.$stderr, -$request->maxStderrBytes);
        }

        if ($stdout !== '') {
            $stdoutBytes += strlen($stdout);

            if ($stdoutBytes > $request->maxStdoutBytes) {
                return ['events' => [], 'failure' => $this->failure(AgentFailureCode::OutputLimit)];
            }

            $buffer .= $stdout;
        }

        while (($newline = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $newline);
            $buffer = substr($buffer, $newline + 1);

            if (strlen($line) > $request->maxLineBytes) {
                return ['events' => $events, 'failure' => $this->failure(AgentFailureCode::OutputLimit)];
            }

            array_push($events, ...$this->parser->parse($line));
        }

        if (strlen($buffer) > $request->maxLineBytes) {
            return ['events' => $events, 'failure' => $this->failure(AgentFailureCode::OutputLimit)];
        }

        if ($final && $buffer !== '') {
            array_push($events, ...$this->parser->parse($buffer));
            $buffer = '';
        }

        return ['events' => $events, 'failure' => null];
    }

    /** @return list<string> */
    private function command(AgentRequest $request): array
    {
        $tools = array_values(array_unique(array_map(
            static fn (string $rule): string => Str::before($rule, '('),
            $request->allowedTools,
        )));
        $command = [
            config()->string('assistant.binary', 'claude'),
            '--safe-mode',
            '-p',
            '--output-format', 'stream-json',
            '--verbose',
            '--model', $request->model,
            '--max-turns', (string) $request->maxTurns,
            '--tools', implode(',', $tools),
            '--allowed-tools', implode(',', $request->allowedTools),
            '--permission-mode', 'dontAsk',
            '--setting-sources', '',
            '--settings', '{}',
            '--disable-slash-commands',
            '--no-chrome',
            '--prompt-suggestions', 'false',
            '--strict-mcp-config',
            '--mcp-config', '{"mcpServers":{}}',
            '--system-prompt', $request->systemPrompt,
        ];

        if ($request->disallowedTools !== []) {
            $command[] = '--disallowed-tools';
            $command[] = implode(',', $request->disallowedTools);
        }

        if ($request->noSessionPersistence) {
            $command[] = '--no-session-persistence';
        }

        if ($request->resume) {
            $command[] = '--resume';
            $command[] = $request->sessionId;
        } else {
            $command[] = '--session-id';
            $command[] = $request->sessionId;
        }

        return $command;
    }

    private function isCancellationRequested(AgentRequest $request): bool
    {
        if ($request->turnId === null) {
            return false;
        }

        return AssistantTurn::query()
            ->whereKey($request->turnId)
            ->whereNotNull('cancel_requested_at')
            ->exists();
    }

    private function workspaceFailure(AgentRequest $request): ?AgentEvent
    {
        $workingDirectory = realpath($request->workingDirectory);
        $workspacePath = realpath($request->workspacePath);

        if (
            $workingDirectory === false
            || $workspacePath === false
            || ! is_dir($workspacePath)
            || ! str_starts_with(
                $workspacePath,
                rtrim($workingDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
            )
        ) {
            return $this->failure(AgentFailureCode::InvalidRequest);
        }

        return null;
    }

    private function resultWithProviderFailure(AgentEvent $result, ?AgentEvent $providerFailure): AgentEvent
    {
        $resultCode = AgentFailureCode::tryFrom((string) $result->code);

        if (
            ! $providerFailure instanceof AgentEvent
            || ! in_array($resultCode, [AgentFailureCode::Execution, AgentFailureCode::ProtocolError], true)
        ) {
            return $result;
        }

        return AgentEvent::result(
            isError: true,
            text: $providerFailure->text,
            costUsd: $result->costUsd,
            durationMs: $result->durationMs,
            code: $providerFailure->code,
            subtype: $result->subtype,
            retryAt: $providerFailure->retryAt,
            stopReason: $result->stopReason,
            usage: $result->usage,
        );
    }

    private function failure(AgentFailureCode $code): AgentEvent
    {
        return AgentEvent::error(
            message: $code->message(),
            code: $code->value,
        );
    }

    private function stop(Process $process, int $graceSeconds): void
    {
        $process->stop(max(0, $graceSeconds), defined('SIGTERM') ? SIGTERM : 15);
    }

    private function logFailure(
        AgentFailureCode $code,
        ?int $exitCode,
        ?string $stderr,
        ?Throwable $exception = null,
        ?string $subtype = null,
    ): void {
        $context = array_filter([
            'code' => $code->value,
            'exit_code' => $exitCode,
            'result_subtype' => $subtype,
            'diagnostic' => $this->redactDiagnostic($stderr),
            'exception' => $exception?->getMessage(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        Log::warning('Claude Code turn failed.', $context);
    }

    private function redactDiagnostic(?string $diagnostic): ?string
    {
        if ($diagnostic === null || trim($diagnostic) === '') {
            return null;
        }

        return (string) Str::of($diagnostic)
            ->replaceMatches('/(?i)(token|key|secret|password|authorization)\s*[:=]\s*\S+/', '$1=[REDACTED]')
            ->replaceMatches('/\b(?:sk-ant|sk-)[A-Za-z0-9_-]+\b/', '[REDACTED]')
            ->replaceMatches('/\b[A-Za-z0-9_-]{24,}\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}\b/', '[REDACTED]')
            ->limit(4096);
    }
}

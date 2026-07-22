<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use Generator;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

/**
 * Streams a headless Claude Code invocation. The process runs with the post
 * workspace as its working directory and a HOME on the persisted storage
 * volume, so subscription credentials and session files survive restarts.
 */
class ClaudeCliRunner implements AgentRunner
{
    public function __construct(private readonly StreamJsonParser $parser) {}

    /** @return Generator<int, AgentEvent> */
    public function stream(AgentRequest $request): Generator
    {
        $process = Process::path($request->workspacePath)
            ->env($this->environment())
            ->timeout($request->timeout)
            ->input($request->prompt)
            ->start($this->command($request));

        $buffer = '';
        $stderr = '';

        try {
            while ($process->running()) {
                $process->ensureNotTimedOut();
                $buffer .= $process->latestOutput();
                $stderr .= $process->latestErrorOutput();

                yield from $this->parseCompleteLines($buffer);

                Sleep::usleep(10_000);
            }

            $buffer .= $process->latestOutput();
            $stderr .= $process->latestErrorOutput();

            yield from $this->parseCompleteLines($buffer);
            yield from $this->parser->parse($buffer);

            if (! $process->wait()->successful() && trim($stderr) !== '') {
                yield AgentEvent::error(trim($stderr));
            }
        } catch (ProcessTimedOutException) {
            yield AgentEvent::error('The assistant took too long and was stopped.');
        } finally {
            if ($process->running()) {
                $process->stop();
            }
        }
    }

    /** @return Generator<int, AgentEvent> */
    private function parseCompleteLines(string &$buffer): Generator
    {
        while (($newline = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $newline);
            $buffer = substr($buffer, $newline + 1);

            yield from $this->parser->parse($line);
        }
    }

    /** @return list<string> */
    private function command(AgentRequest $request): array
    {
        $command = [
            config()->string('assistant.binary', 'claude'),
            '-p',
            '--output-format', 'stream-json',
            '--verbose',
            '--model', $request->model,
            '--max-turns', (string) $request->maxTurns,
            '--allowed-tools', implode(' ', $request->allowedTools),
            '--disallowed-tools', implode(' ', $request->disallowedTools),
            '--strict-mcp-config',
            '--mcp-config', '{"mcpServers":{}}',
            '--append-system-prompt', $request->systemPrompt,
        ];

        if ($request->resume) {
            $command[] = '--resume';
            $command[] = $request->sessionId;
        } else {
            $command[] = '--session-id';
            $command[] = $request->sessionId;
        }

        return $command;
    }

    /** @return array<string, string> */
    private function environment(): array
    {
        $environment = [
            'HOME' => config()->string('assistant.home', storage_path('app/assistant/home')),
            'DISABLE_TELEMETRY' => '1',
            'DISABLE_ERROR_REPORTING' => '1',
            'CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC' => '1',
        ];

        foreach (['CLAUDE_CODE_OAUTH_TOKEN', 'ANTHROPIC_API_KEY', 'PATH'] as $passthrough) {
            $value = getenv($passthrough);

            if (is_string($value) && $value !== '') {
                $environment[$passthrough] = $value;
            }
        }

        return $environment;
    }
}

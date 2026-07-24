<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\ValueObjects\ClaudeRuntimeHealth;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use Symfony\Component\Process\Process;
use Throwable;

final readonly class ClaudeRuntimeInspector
{
    public function __construct(
        private ClaudeProcessEnvironment $environment,
        private Filesystem $files,
    ) {}

    public function inspect(): ClaudeRuntimeHealth
    {
        $errors = [];
        $version = null;

        if ($this->environment->oauthToken() === null) {
            $errors[] = 'CLAUDE_CODE_OAUTH_TOKEN is not configured.';
        }

        foreach ([
            config()->string('assistant.home', storage_path('app/assistant/home')),
            config()->string('assistant.workspaces', storage_path('app/ai-workspaces')),
        ] as $directory) {
            try {
                $this->files->ensureDirectoryExists($directory, 0700);
            } catch (Throwable) {
                $errors[] = $directory.' could not be created.';

                continue;
            }

            if (! $this->files->isDirectory($directory) || ! is_writable($directory)) {
                $errors[] = $directory.' is not writable.';
            }
        }

        try {
            $versionProcess = $this->run(['--version']);
            $version = $this->versionFrom($versionProcess->getOutput());
            $expectedVersion = config()->string('assistant.claude_version', '2.1.218');

            if (! $versionProcess->isSuccessful() || $version !== $expectedVersion) {
                $errors[] = 'Claude Code '.$expectedVersion.' is required.';
            }
        } catch (Throwable) {
            $errors[] = 'The Claude Code executable could not be started.';
        }

        if ($this->environment->oauthToken() !== null) {
            try {
                $authProcess = $this->run(['auth', 'status', '--json']);
                $auth = $this->authStatus($authProcess->getOutput());

                if (
                    ! $authProcess->isSuccessful()
                    || ($auth['loggedIn'] ?? false) !== true
                    || ! in_array($auth['authMethod'] ?? null, ['claude.ai', 'oauth_token'], true)
                ) {
                    $errors[] = 'Claude Code subscription OAuth is not ready.';
                }
            } catch (Throwable) {
                $errors[] = 'Claude Code authentication could not be checked.';
            }
        }

        return new ClaudeRuntimeHealth($errors === [], $version, $errors);
    }

    /** @param list<string> $arguments */
    private function run(array $arguments): Process
    {
        $process = new Process(
            [config()->string('assistant.binary', 'claude'), ...$arguments],
            config()->string('assistant.workspaces', storage_path('app/ai-workspaces')),
            $this->environment->variables(),
            timeout: 10,
        );
        $process->run();

        return $process;
    }

    private function versionFrom(string $output): ?string
    {
        return preg_match('/\A(\d+\.\d+\.\d+)\b/', trim($output), $matches) === 1
            ? $matches[1]
            : null;
    }

    /** @return array<string, mixed> */
    private function authStatus(string $output): array
    {
        try {
            $status = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($status)) {
            return [];
        }

        $result = [];

        foreach ($status as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

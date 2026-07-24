<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

final class ClaudeProcessEnvironment
{
    /** @return array<string, string|false> */
    public function variables(): array
    {
        $environment = [];

        foreach ($this->inheritedVariableNames() as $name) {
            $environment[$name] = false;
        }

        $environment['HOME'] = config()->string('assistant.home', storage_path('app/assistant/home'));
        $environment['PATH'] = config()->string('assistant.process.path', '/usr/local/bin:/usr/bin:/bin');
        $environment['TMPDIR'] = config()->string('assistant.process.tmpdir', sys_get_temp_dir());
        $environment['LANG'] = config()->string('assistant.process.locale', 'C.UTF-8');
        $environment['LC_ALL'] = config()->string('assistant.process.locale', 'C.UTF-8');
        $environment['DISABLE_TELEMETRY'] = '1';
        $environment['DISABLE_ERROR_REPORTING'] = '1';
        $environment['CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC'] = '1';
        $environment['CLAUDE_CODE_DISABLE_FEEDBACK_SURVEY'] = '1';
        $environment['CLAUDE_CODE_MAX_RETRIES'] = (string) config()->integer('assistant.process.max_retries', 2);
        $environment['API_TIMEOUT_MS'] = (string) config()->integer('assistant.process.api_timeout_ms', 120_000);
        $environment['CLAUDE_ASYNC_AGENT_STALL_TIMEOUT_MS'] = (string) (
            config()->integer('assistant.idle_timeout', 150) * 1000
        );
        $environment['CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS'] = '5000';

        $oauthToken = $this->oauthToken();

        if ($oauthToken !== null) {
            $environment['CLAUDE_CODE_OAUTH_TOKEN'] = $oauthToken;
        }

        return $environment;
    }

    public function oauthToken(): ?string
    {
        $oauthToken = config('assistant.oauth_token');

        if (is_string($oauthToken) && trim($oauthToken) !== '') {
            return $oauthToken;
        }

        // Config may be cached by an init container that intentionally does
        // not receive the assistant secret. Read the web container's runtime
        // environment as a fallback without weakening test isolation.
        if (app()->environment('testing')) {
            return null;
        }

        $runtimeToken = getenv('CLAUDE_CODE_OAUTH_TOKEN');

        return is_string($runtimeToken) && trim($runtimeToken) !== ''
            ? $runtimeToken
            : null;
    }

    /** @return list<string> */
    private function inheritedVariableNames(): array
    {
        $names = [];

        foreach ([getenv(), $_ENV, $_SERVER] as $source) {
            foreach (array_keys($source) as $name) {
                if (is_string($name) && preg_match('/\A[A-Za-z_]\w*\z/', $name) === 1) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }
}

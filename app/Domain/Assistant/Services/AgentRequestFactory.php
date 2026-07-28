<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Prompts\SystemPromptBuilder;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Enums\AuthoringPersonality;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use Illuminate\Support\Str;

/**
 * Assembles the {@see AgentRequest} for a turn from configuration and the
 * turn's task type. Conversational tasks resume the post's persistent Claude
 * Code session; one-shot tasks run under a throwaway session id. Centralizing
 * this keeps every streaming controller down to "build the prompt, hand it off".
 */
final readonly class AgentRequestFactory
{
    public function __construct(
        private WorkspaceManager $workspace,
        private SystemPromptBuilder $systemPrompt,
    ) {}

    public function forTurn(Post $post, AssistantTurn $turn, string $prompt): AgentRequest
    {
        $taskType = $turn->task_type;
        [$sessionId, $resume] = $this->session($turn, $taskType);
        $runDirectory = 'runs/'.$turn->run_token;
        $workspacePath = $this->workspace->pathFor($post, $turn);

        return new AgentRequest(
            prompt: <<<PROMPT
            The article files in the task below are inside {$workspacePath}/. When calling file tools, resolve article filenames to absolute paths such as {$workspacePath}/draft.md and {$workspacePath}/meta.json. Never use a root-level /runs path.

            {$prompt}
            PROMPT,
            workingDirectory: $this->workspace->rootFor($post),
            workspacePath: $workspacePath,
            model: $taskType->model(),
            sessionId: $sessionId,
            resume: $resume,
            systemPrompt: $this->systemPrompt->build(
                $workspacePath,
                $this->authoringStyle($post),
            ),
            enabledTools: $this->enabledTools($taskType, $turn),
            allowedToolRules: $this->allowedToolRules($taskType, $turn, $runDirectory),
            disallowedTools: $this->disallowedTools($taskType, $turn),
            maxTurns: $taskType->maxTurns(),
            timeout: config()->integer('assistant.turn_timeout', 300),
            startupTimeout: config()->integer('assistant.startup_timeout', 30),
            idleTimeout: config()->integer('assistant.idle_timeout', 120),
            terminationGrace: config()->integer('assistant.termination_grace', 3),
            heartbeatInterval: config()->integer('assistant.heartbeat_interval', 10),
            cancellationPollInterval: config()->integer('assistant.cancellation_poll_interval', 1),
            maxStdoutBytes: config()->integer('assistant.limits.stdout_bytes', 16 * 1024 * 1024),
            maxStderrBytes: config()->integer('assistant.limits.stderr_bytes', 64 * 1024),
            maxLineBytes: config()->integer('assistant.limits.line_bytes', 4 * 1024 * 1024),
            maxEventTextBytes: config()->integer('assistant.limits.event_text_bytes', 128 * 1024),
            maxNarrationBytes: config()->integer('assistant.limits.narration_bytes', 128 * 1024),
            noSessionPersistence: ! $taskType->usesPersistentSession(),
            turnId: $turn->id,
            runToken: $turn->run_token,
        );
    }

    /** @return array{string, bool} */
    private function session(AssistantTurn $turn, AssistantTaskType $taskType): array
    {
        if (! $taskType->usesPersistentSession()) {
            return [(string) Str::uuid(), false];
        }

        /** @var AssistantSession $session */
        $session = $turn->session()->firstOrFail();

        return [$session->claude_session_id, $session->resume_ready];
    }

    /** @return list<string> */
    private function configuredTools(string $key): array
    {
        $tools = config('assistant.'.$key);

        return is_array($tools) ? array_values(array_filter($tools, is_string(...))) : [];
    }

    /** @return list<string> */
    private function enabledTools(AssistantTaskType $taskType, AssistantTurn $turn): array
    {
        $tools = match ($taskType) {
            AssistantTaskType::Transform, AssistantTaskType::Metadata, AssistantTaskType::Images => [
                'Read',
                'Edit',
            ],
            AssistantTaskType::Chat, AssistantTaskType::Outline => [
                'Read',
                'Edit',
                'Write',
            ],
            AssistantTaskType::Plans, AssistantTaskType::Draft => [
                'Read',
                'Edit',
            ],
            AssistantTaskType::Research => [
                'Read',
                'Edit',
                'WebSearch',
            ],
        };

        if ($this->webFetchDomains($taskType, $turn) !== []) {
            $tools[] = 'WebFetch';
        }

        return $tools;
    }

    /** @return list<string> */
    private function allowedToolRules(
        AssistantTaskType $taskType,
        AssistantTurn $turn,
        string $runDirectory,
    ): array {
        $draft = $runDirectory.'/draft.md';
        $metadata = $runDirectory.'/meta.json';
        $outline = $runDirectory.'/outline.md';
        $output = $runDirectory.'/authoring-output.json';

        $rules = match ($taskType) {
            AssistantTaskType::Transform => [
                "Read({$draft})",
                "Edit({$draft})",
            ],
            AssistantTaskType::Metadata => [
                "Read({$draft})",
                "Read({$metadata})",
                "Edit({$metadata})",
            ],
            AssistantTaskType::Images => [
                "Read({$runDirectory}/**)",
                "Edit({$draft})",
                "Edit({$metadata})",
            ],
            AssistantTaskType::Chat, AssistantTaskType::Outline => [
                "Read({$runDirectory}/**)",
                "Edit({$draft})",
                "Edit({$metadata})",
                "Edit({$outline})",
            ],
            AssistantTaskType::Plans, AssistantTaskType::Draft => [
                "Read({$runDirectory}/**)",
                "Edit({$output})",
            ],
            AssistantTaskType::Research => [
                "Read({$runDirectory}/**)",
                "Edit({$output})",
                'WebSearch',
            ],
        };

        foreach ($this->webFetchDomains($taskType, $turn) as $domain) {
            $rules[] = "WebFetch(domain:{$domain})";
        }

        return $rules;
    }

    /** @return list<string> */
    private function disallowedTools(AssistantTaskType $taskType, AssistantTurn $turn): array
    {
        $allowedExternalTools = [];

        if ($taskType === AssistantTaskType::Research) {
            $allowedExternalTools[] = 'WebSearch';
        }

        if ($this->webFetchDomains($taskType, $turn) !== []) {
            $allowedExternalTools[] = 'WebFetch';
        }

        return array_values(array_diff(
            $this->configuredTools('disallowed_tools'),
            $allowedExternalTools,
        ));
    }

    /**
     * Domains the agent may fetch for this turn, or none.
     *
     * Only the guided authoring prompts tell the agent to read approved URL
     * sources, so only they get network access — and then just for the exact
     * hosts the author approved. The legacy editing task types stay offline
     * even if a turn context ever starts carrying source domains.
     *
     * SafeExternalSourceUrl screens those hosts when the author adds them, but
     * it cannot bind the DNS resolution the CLI performs at fetch time. Keeping
     * this list to approved hosts on prompts that need them is the mitigation.
     *
     * @return list<string>
     */
    private function webFetchDomains(AssistantTaskType $taskType, AssistantTurn $turn): array
    {
        return match ($taskType) {
            AssistantTaskType::Plans,
            AssistantTaskType::Draft,
            AssistantTaskType::Research => $this->sourceDomains($turn),
            AssistantTaskType::Chat,
            AssistantTaskType::Outline,
            AssistantTaskType::Transform,
            AssistantTaskType::Metadata,
            AssistantTaskType::Images => [],
        };
    }

    /** @return list<string> */
    private function sourceDomains(AssistantTurn $turn): array
    {
        $domains = ($turn->context ?? [])['source_domains'] ?? [];

        if (! is_array($domains)) {
            return [];
        }

        $safeDomains = [];

        foreach ($domains as $domain) {
            if (
                is_string($domain)
                && Str::isMatch('/\A[a-z0-9.-]+\z/', $domain)
                && ! Str::contains($domain, '..')
                && ! Str::startsWith($domain, '.')
                && ! Str::endsWith($domain, '.')
            ) {
                $safeDomains[] = Str::lower($domain);
            }
        }

        return array_values(array_unique($safeDomains));
    }

    private function authoringStyle(Post $post): string
    {
        $journey = PostAuthoringJourney::query()->whereBelongsTo($post)->first();
        $personality = $journey === null
            ? AuthoringPersonality::Friendly
            : $journey->personality;

        return match ($personality) {
            AuthoringPersonality::Friendly => 'Use a warm, encouraging voice with plain language and helpful explanations.',
            AuthoringPersonality::Professional => 'Use a clear, polished, credible voice with precise language and restrained phrasing.',
            AuthoringPersonality::Creative => 'Use an expressive, curious voice with vivid but accurate language and fresh structure.',
            AuthoringPersonality::Custom => $this->customAuthoringStyle($journey?->custom_instructions),
        };
    }

    private function customAuthoringStyle(?string $instructions): string
    {
        if ($instructions === null || trim($instructions) === '') {
            return 'Use a clear voice and follow the author’s explicit requests in each message.';
        }

        return 'The author’s custom style instructions, encoded as a JSON string, are: '.json_encode(
            $instructions,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}

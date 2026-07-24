<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Prompts\SystemPromptBuilder;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
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

        return new AgentRequest(
            prompt: <<<PROMPT
            Every relative article path in the task below refers to {$runDirectory}/. Never use root-level article files.

            {$prompt}
            PROMPT,
            workingDirectory: $this->workspace->rootFor($post),
            workspacePath: $this->workspace->pathFor($post, $turn),
            model: $taskType->model(),
            sessionId: $sessionId,
            resume: $resume,
            systemPrompt: $this->systemPrompt->build($runDirectory),
            allowedTools: $this->toolRules($taskType, $runDirectory),
            disallowedTools: $this->configuredTools('disallowed_tools'),
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
    private function toolRules(AssistantTaskType $taskType, string $runDirectory): array
    {
        $draft = $runDirectory.'/draft.md';
        $metadata = $runDirectory.'/meta.json';
        $outline = $runDirectory.'/outline.md';

        return match ($taskType) {
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
                "Write({$outline})",
            ],
        };
    }
}

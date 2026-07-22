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

        return new AgentRequest(
            prompt: $prompt,
            workspacePath: $this->workspace->pathFor($post),
            model: $taskType->model(),
            sessionId: $sessionId,
            resume: $resume,
            systemPrompt: $this->systemPrompt->build(),
            allowedTools: $this->configuredTools('allowed_tools'),
            disallowedTools: $this->configuredTools('disallowed_tools'),
            maxTurns: $taskType->maxTurns(),
            timeout: config()->integer('assistant.turn_timeout', 300),
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

        return [$session->claude_session_id, $session->hasConversationBefore($turn->id)];
    }

    /** @return list<string> */
    private function configuredTools(string $key): array
    {
        $tools = config('assistant.'.$key);

        return is_array($tools) ? array_values(array_filter($tools, is_string(...))) : [];
    }
}

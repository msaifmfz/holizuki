<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\StreamsAssistantTurn;
use App\Http\Admin\Requests\Assistant\ChatTurnRequest;
use App\Http\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One co-writer conversation turn. Chat turns resume the post's persistent
 * Claude Code session so the conversation keeps its memory; the workspace is
 * refreshed from the live post before the agent runs.
 */
class AssistantChatController extends Controller
{
    use StreamsAssistantTurn;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly StartAssistantTurn $startTurn,
        private readonly CompleteAssistantTurn $completeTurn,
        private readonly FailAssistantTurn $failTurn,
        private readonly AgentRequestFactory $agentRequests,
        private readonly AssistantAvailability $availability,
        private readonly AssistantRunLease $runLease,
        private readonly WorkspaceManager $workspace,
    ) {}

    public function __invoke(ChatTurnRequest $request, Post $post): StreamedResponse
    {
        $this->availability->ensureCanStart();

        $turn = $this->startTurn->handle(
            $post,
            AssistantTaskType::Chat,
            $request->message(),
            $request->requestId(),
        );

        return $this->streamAssistantTurn(
            $this->runner,
            $this->completeTurn,
            $this->failTurn,
            $this->runLease,
            $this->availability,
            $this->workspace,
            $post,
            $turn,
            fn (): AgentRequest => $this->agentRequests->forTurn($post, $turn, $request->message()),
        );
    }
}

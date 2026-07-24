<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Prompts\OutlinePrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\StreamsAssistantTurn;
use App\Http\Admin\Requests\Assistant\OutlineStepRequest;
use App\Http\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The idea → outline → draft wizard. Both steps run on the post's
 * persistent session, so outline refinement happens through ordinary chat
 * between them and the drafting step remembers the whole discussion.
 */
class AssistantOutlineController extends Controller
{
    use StreamsAssistantTurn;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly StartAssistantTurn $startTurn,
        private readonly CompleteAssistantTurn $completeTurn,
        private readonly FailAssistantTurn $failTurn,
        private readonly AgentRequestFactory $agentRequests,
        private readonly OutlinePrompt $outlinePrompt,
        private readonly AssistantAvailability $availability,
        private readonly AssistantRunLease $runLease,
        private readonly WorkspaceManager $workspace,
    ) {}

    public function __invoke(OutlineStepRequest $request, Post $post): StreamedResponse
    {
        $this->availability->ensureCanStart();

        $prompt = $request->step() === 'start'
            ? $this->outlinePrompt->start((string) $request->message())
            : $this->outlinePrompt->draft($request->message());

        $turn = $this->startTurn->handle(
            $post,
            AssistantTaskType::Outline,
            $request->message() ?? 'Draft the article from the outline.',
            $request->requestId(),
            [
                'step' => $request->step(),
            ],
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
            fn (): AgentRequest => $this->agentRequests->forTurn($post, $turn, $prompt),
        );
    }
}

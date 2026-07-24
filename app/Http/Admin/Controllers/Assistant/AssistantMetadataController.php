<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Prompts\MetadataPrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\StreamsAssistantTurn;
use App\Http\Admin\Requests\Assistant\MetadataGenerateRequest;
use App\Http\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantMetadataController extends Controller
{
    use StreamsAssistantTurn;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly StartAssistantTurn $startTurn,
        private readonly CompleteAssistantTurn $completeTurn,
        private readonly FailAssistantTurn $failTurn,
        private readonly AgentRequestFactory $agentRequests,
        private readonly MetadataPrompt $metadataPrompt,
        private readonly AssistantAvailability $availability,
        private readonly AssistantRunLease $runLease,
        private readonly WorkspaceManager $workspace,
    ) {}

    public function __invoke(MetadataGenerateRequest $request, Post $post): StreamedResponse
    {
        $this->availability->ensureCanStart();

        $prompt = $this->metadataPrompt->build($request->fields());
        $turn = $this->startTurn->handle(
            $post,
            AssistantTaskType::Metadata,
            $prompt,
            $request->requestId(),
            ['fields' => $request->fields()],
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

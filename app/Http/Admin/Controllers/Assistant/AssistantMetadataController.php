<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Prompts\MetadataPrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
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
    ) {}

    public function __invoke(MetadataGenerateRequest $request, Post $post): StreamedResponse
    {
        abort_unless(config()->boolean('assistant.enabled', true), 404);

        $prompt = $this->metadataPrompt->build($request->fields());

        try {
            $turn = $this->startTurn->handle($post, AssistantTaskType::Metadata, $prompt, ['fields' => $request->fields()]);
        } catch (AssistantBusyException $exception) {
            abort(409, $exception->getMessage());
        }

        $agentRequest = $this->agentRequests->forTurn($post, $turn, $prompt);

        return $this->streamAssistantTurn($this->runner, $this->completeTurn, $this->failTurn, $post, $turn, $agentRequest);
    }
}

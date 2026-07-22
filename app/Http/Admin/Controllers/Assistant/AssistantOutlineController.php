<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Prompts\OutlinePrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
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
    ) {}

    public function __invoke(OutlineStepRequest $request, Post $post): StreamedResponse
    {
        abort_unless(config()->boolean('assistant.enabled', true), 404);

        $prompt = $request->step() === 'start'
            ? $this->outlinePrompt->start((string) $request->message())
            : $this->outlinePrompt->draft($request->message());

        try {
            $turn = $this->startTurn->handle($post, AssistantTaskType::Outline, $request->message() ?? 'Draft the article from the outline.', [
                'step' => $request->step(),
            ]);
        } catch (AssistantBusyException $exception) {
            abort(409, $exception->getMessage());
        }

        $agentRequest = $this->agentRequests->forTurn($post, $turn, $prompt);

        return $this->streamAssistantTurn($this->runner, $this->completeTurn, $this->failTurn, $post, $turn, $agentRequest);
    }
}

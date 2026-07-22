<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Prompts\TransformPrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\StreamsAssistantTurn;
use App\Http\Admin\Requests\Assistant\TransformRequest;
use App\Http\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A one-shot selection transform: rewrite, expand, shorten, simplify, or a
 * custom instruction applied to the passage the author highlighted. The
 * result streams back as ordinary reviewable body changes.
 */
class AssistantTransformController extends Controller
{
    use StreamsAssistantTurn;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly StartAssistantTurn $startTurn,
        private readonly CompleteAssistantTurn $completeTurn,
        private readonly FailAssistantTurn $failTurn,
        private readonly AgentRequestFactory $agentRequests,
        private readonly TransformPrompt $transformPrompt,
    ) {}

    public function __invoke(TransformRequest $request, Post $post): StreamedResponse
    {
        abort_unless(config()->boolean('assistant.enabled', true), 404);

        $prompt = $this->transformPrompt->build(
            $request->selection(),
            $request->preset(),
            $request->instruction(),
        );

        try {
            $turn = $this->startTurn->handle($post, AssistantTaskType::Transform, $prompt, [
                'preset' => $request->preset(),
                'selection' => Str::limit($request->selection(), 200),
            ]);
        } catch (AssistantBusyException $exception) {
            abort(409, $exception->getMessage());
        }

        $agentRequest = $this->agentRequests->forTurn($post, $turn, $prompt);

        return $this->streamAssistantTurn($this->runner, $this->completeTurn, $this->failTurn, $post, $turn, $agentRequest);
    }
}

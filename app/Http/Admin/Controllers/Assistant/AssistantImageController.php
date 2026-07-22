<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Prompts\ImageReviewPrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\StreamsAssistantTurn;
use App\Http\Admin\Requests\Assistant\ImageReviewRequest;
use App\Http\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One-shot photo review: alt text, captions, and placement suggestions for
 * the post's images, produced by a vision-capable agent that actually looks
 * at each file.
 */
class AssistantImageController extends Controller
{
    use StreamsAssistantTurn;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly StartAssistantTurn $startTurn,
        private readonly CompleteAssistantTurn $completeTurn,
        private readonly FailAssistantTurn $failTurn,
        private readonly AgentRequestFactory $agentRequests,
        private readonly ImageReviewPrompt $imagePrompt,
    ) {}

    public function __invoke(ImageReviewRequest $request, Post $post): StreamedResponse
    {
        abort_unless(config()->boolean('assistant.enabled', true), 404);

        abort_if(
            $post->media()->doesntExist() && $post->featured_image_path === null,
            422,
            'This post has no images to review yet.',
        );

        $prompt = $this->imagePrompt->build();

        try {
            $turn = $this->startTurn->handle($post, AssistantTaskType::Images, 'Review the images: alt text, captions, and placement.');
        } catch (AssistantBusyException $exception) {
            abort(409, $exception->getMessage());
        }

        $agentRequest = $this->agentRequests->forTurn($post, $turn, $prompt);

        return $this->streamAssistantTurn($this->runner, $this->completeTurn, $this->failTurn, $post, $turn, $agentRequest);
    }
}

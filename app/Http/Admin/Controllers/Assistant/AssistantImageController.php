<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Prompts\ImageReviewPrompt;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AgentRequest;
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
        private readonly AssistantAvailability $availability,
        private readonly AssistantRunLease $runLease,
        private readonly WorkspaceManager $workspace,
    ) {}

    public function __invoke(ImageReviewRequest $request, Post $post): StreamedResponse
    {
        $this->availability->ensureCanStart();

        abort_if(
            $post->media()->doesntExist() && $post->featured_image_path === null,
            422,
            'This post has no images to review yet.',
        );

        $prompt = $this->imagePrompt->build();

        $turn = $this->startTurn->handle(
            $post,
            AssistantTaskType::Images,
            'Review the images: alt text, captions, and placement.',
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
            fn (): AgentRequest => $this->agentRequests->forTurn($post, $turn, $prompt),
        );
    }
}

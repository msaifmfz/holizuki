<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\RequestAuthoringTask;
use App\Domain\Assistant\Actions\SelectAuthoringArtifact;
use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Exceptions\StaleAuthoringArtifactException;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AuthoringArtifactFreshness;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Assistant\Services\AuthoringImageFlowReadiness;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Publishing\Actions\SavePostDraft;
use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use App\Http\Admin\Concerns\BuildsAutosaveResponse;
use App\Http\Admin\Concerns\DispatchesAuthoringTasks;
use App\Http\Admin\Requests\Assistant\ApplyAuthoringDraftRequest;
use App\Http\Admin\Requests\Assistant\GenerateAuthoringDraftRequest;
use App\Http\Admin\Requests\Assistant\SelectAuthoringArtifactRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AuthoringDraftController extends Controller
{
    use BuildsAutosaveResponse;
    use DispatchesAuthoringTasks;

    public function __construct(private readonly AuthoringArtifactFreshness $freshness) {}

    public function store(
        GenerateAuthoringDraftRequest $request,
        Post $post,
        AuthoringArtifact $artifact,
        AssistantAvailability $availability,
        AuthoringContextFactory $contexts,
        AuthoringImageFlowReadiness $imageFlowReadiness,
        RequestAuthoringTask $requestTask,
    ): JsonResponse {
        $this->ensureCurrentSelectedPlan(
            $post,
            $artifact,
            $this->freshness,
            'Choose this plan before drafting the article.',
        );
        $imageFlowReadiness->ensureReadyForDraft($post, $artifact);

        return $this->dispatchAuthoringTask(
            $post,
            $request->authenticatedUser(),
            AuthoringTaskKind::Draft,
            $request->requestId(),
            $contexts->forDraft($post, $artifact),
            $availability,
            $contexts,
            $requestTask,
        );
    }

    public function show(Post $post, AuthoringArtifact $artifact): JsonResponse
    {
        Gate::authorize('update', $post);
        abort_unless($artifact->post_id === $post->id && $artifact->type === AuthoringArtifactType::Draft, 404);
        $selection = AuthoringSelection::query()->whereBelongsTo($post)->first();

        return response()->json([
            'draft' => $this->authoringArtifactData(
                $artifact->load('parent'),
                $post,
                $selection,
                $this->freshness->isStale($post, $artifact),
            ),
        ]);
    }

    public function select(
        SelectAuthoringArtifactRequest $request,
        Post $post,
        AuthoringArtifact $artifact,
        SelectAuthoringArtifact $selectArtifact,
    ): JsonResponse {
        Gate::authorize('update', $post);
        abort_unless($artifact->post_id === $post->id && $artifact->type === AuthoringArtifactType::Draft, 404);
        $selection = $selectArtifact->handle(
            $post,
            $artifact,
            AuthoringArtifactType::Draft,
            $request->authenticatedUser(),
        );

        return response()->json(['selection' => $this->authoringSelectionData($selection)]);
    }

    public function apply(
        ApplyAuthoringDraftRequest $request,
        Post $post,
        AuthoringArtifact $artifact,
        MarkdownParser $markdownParser,
        WorkspaceManager $workspace,
        SelectAuthoringArtifact $selectArtifact,
        SavePostDraft $saveDraft,
    ): JsonResponse {
        abort_unless($artifact->post_id === $post->id && $artifact->type === AuthoringArtifactType::Draft, 404);

        if (
            $this->freshness->isStale($post, $artifact)
            || $request->integer('lock_version') !== $post->lock_version
        ) {
            throw new StaleAuthoringArtifactException;
        }

        $bodyMarkdown = $artifact->payload['body_markdown'] ?? null;

        if (! is_string($bodyMarkdown)) {
            throw ValidationException::withMessages(['draft' => 'The selected draft has no valid article body.']);
        }

        $document = $markdownParser->parse($bodyMarkdown, $workspace->mediaMap($post));
        Validator::make(
            ['body' => $document->toArray()],
            ['body' => [new ValidRichTextDocument]],
        )->validate();

        [$updatedPost, $selection] = DB::transaction(function () use (
            $post,
            $artifact,
            $request,
            $selectArtifact,
            $saveDraft,
            $document,
        ): array {
            $selection = $selectArtifact->handle(
                $post,
                $artifact,
                AuthoringArtifactType::Draft,
                $request->authenticatedUser(),
            );
            $updatedPost = $saveDraft->handle($post, [
                'lock_version' => $request->integer('lock_version'),
                'title' => $artifact->payload['title'] ?? null,
                'excerpt' => $artifact->payload['excerpt'] ?? null,
                'body' => $document->toArray(),
            ], $request->authenticatedUser());

            return [$updatedPost, $selection];
        });

        $savedDraft = $updatedPost->draft()->with('tags:id,name')->firstOrFail();

        return response()->json([
            'post' => [
                ...$this->autosavePayload($updatedPost),
                'title' => $savedDraft->title,
                'slug_is_manual' => $savedDraft->slug_is_manual,
                'excerpt' => $savedDraft->excerpt,
                'category_id' => $savedDraft->category_id,
                'author_id' => $savedDraft->author_id,
                'seo_title' => $savedDraft->seo_title,
                'meta_description' => $savedDraft->meta_description,
                'canonical_url' => $savedDraft->canonical_url,
                'og_title' => $savedDraft->og_title,
                'og_description' => $savedDraft->og_description,
                'noindex' => $savedDraft->noindex,
                'featured_image_alt' => $savedDraft->featured_image_alt,
                'featured_image_caption' => $savedDraft->featured_image_caption,
                'tags' => $savedDraft->tags->pluck('name')->values()->all(),
                'body' => $savedDraft->body instanceof RichTextDocument
                    ? $savedDraft->body->toArray()
                    : null,
            ],
            'selection' => $this->authoringSelectionData($selection),
            'applied_artifact_id' => $artifact->public_id,
        ]);
    }
}

<?php

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Jobs\RunAuthoringTask;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAgentRunner;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-authoring-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);
    config()->set('assistant.global_lock_key', 'assistant:authoring-test-'.uniqid());
    config()->set('assistant.availability.cache_key', 'assistant:authoring-availability-'.uniqid());

    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Original title',
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Original working draft.']],
            ]],
        ],
    ]);
    $this->journey = PostAuthoringJourney::factory()->for($this->post, 'post')->create([
        'brief' => [
            'topic' => 'Community gardens',
            'audience' => 'New volunteers',
            'purpose' => 'Teach',
        ],
    ]);
    $this->source = PostSource::factory()
        ->approved()
        ->for($this->post, 'post')
        ->for($this->user, 'creator')
        ->create([
            'content' => 'Gardens can strengthen local social ties.',
            'visibility' => 'public',
        ]);
});

afterEach(function (): void {
    Cache::lock(config()->string('assistant.global_lock_key'))->forceRelease();
    File::deleteDirectory($this->workspaceRoot);
});

function runQueuedAuthoringJob(AuthoringTask $task, array $script): FakeAgentRunner
{
    $runner = new FakeAgentRunner($script);
    app()->instance(AgentRunner::class, $runner);
    $job = new RunAuthoringTask($task->id, $task->post_id);
    app()->call($job->handle(...));

    return $runner;
}

test('a plans job stores exactly three immutable review artifacts without editing the draft', function (): void {
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forPlans($this->post);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $context),
    ]);
    $originalBody = $this->post->draft()->firstOrFail()->body?->toArray();

    $runner = runQueuedAuthoringJob($task, [
        function (AgentRequest $request): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'plans' => [
                    [
                        'title' => 'A practical guide',
                        'angle' => 'Step-by-step',
                        'sections' => ['Why gardens matter', 'Find a plot'],
                        'estimated_length' => 'medium',
                        'source_ids' => [$this->source->id],
                    ],
                    [
                        'title' => 'Neighbors who grow together',
                        'angle' => 'Human stories',
                        'sections' => ['Meet the neighbors', 'Shared harvests'],
                        'estimated_length' => 'long',
                        'source_ids' => [$this->source->id],
                    ],
                    [
                        'title' => 'Your first garden weekend',
                        'angle' => 'Fast-start checklist',
                        'sections' => ['Before Saturday', 'Planting day'],
                        'estimated_length' => 'short',
                        'source_ids' => [],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Created three plans.', 0.01, 1200),
    ]);

    expect($task->refresh()->error_message)->toBeNull()
        ->and($task->status)->toBe(AuthoringTaskStatus::Completed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::Plan)->count())->toBe(3)
        ->and($task->artifacts()->pluck('position')->all())->toBe([1, 2, 3])
        ->and(AssistantChange::query()->count())->toBe(0)
        ->and($this->post->draft()->firstOrFail()->body?->toArray())->toBe($originalBody)
        ->and($runner->lastRequest?->enabledTools)->not->toContain('WebSearch')
        ->and($runner->lastRequest?->allowedToolRules)->toContain(
            'Edit(runs/'.$task->turn()->firstOrFail()->run_token.'/authoring-output.json)',
        );
});

test('invalid plan counts fail safely without leaving partial artifacts', function (): void {
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forPlans($this->post);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $context),
    ]);

    runQueuedAuthoringJob($task, [
        function (AgentRequest $request): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'plans' => [],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Done.', null, 100),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Failed)
        ->and($task->artifacts()->count())->toBe(0)
        ->and(AssistantChange::query()->count())->toBe(0);
});

test('plans may share a source with each other but not repeat one within a plan', function (
    array $sourceIdsPerPlan,
    bool $accepted,
): void {
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forPlans($this->post);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $context),
    ]);
    $sourceId = $this->source->id;

    runQueuedAuthoringJob($task, [
        function (AgentRequest $request) use ($sourceIdsPerPlan, $sourceId): void {
            $plans = [];

            foreach ($sourceIdsPerPlan as $index => $sourceIds) {
                $plans[] = [
                    'title' => 'Plan '.$index,
                    'angle' => 'An angle',
                    'sections' => ['Section A '.$index, 'Section B '.$index],
                    'estimated_length' => 'medium',
                    'source_ids' => array_map(
                        static fn (int $offset): int => $sourceId + $offset,
                        $sourceIds,
                    ),
                ];
            }

            file_put_contents(
                $request->workspacePath.'/authoring-output.json',
                json_encode(['plans' => $plans], JSON_THROW_ON_ERROR),
            );
        },
        AgentEvent::result(false, 'Done.', null, 100),
    ]);

    expect($task->refresh()->status)->toBe(
        $accepted ? AuthoringTaskStatus::Completed : AuthoringTaskStatus::Failed,
    )->and($task->artifacts()->count())->toBe($accepted ? 3 : 0);
})->with([
    'the same source across plans is fine' => [[[0], [0], [0]], true],
    'a source repeated inside one plan is not' => [[[0, 0], [0], []], false],
]);

test('an image guidance job stores per-photo guidance and group suggestions for its plan', function (): void {
    $media = PostMedia::factory()->for($this->post)->create();
    $secondMedia = PostMedia::factory()->for($this->post)->create();
    Storage::disk('public')->put($media->path, 'image-bytes');
    Storage::disk('public')->put($secondMedia->path, 'second-image-bytes');
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'base_lock_version' => $this->post->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical guide',
                'angle' => 'Step-by-step',
                'sections' => ['Why gardens matter', 'Getting started'],
                'estimated_length' => 'medium',
                'source_ids' => [$this->source->id],
            ],
        ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImageGuidance($this->post, $plan);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(
            AuthoringTaskKind::ImageGuidance,
            $context,
        ),
    ]);

    $runner = runQueuedAuthoringJob($task, [
        function (AgentRequest $request) use ($media, $secondMedia): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'photo_guidance' => [
                    [
                        'media_id' => $media->id,
                        'suggested_name' => 'Neighbors preparing a raised bed',
                        'alt_text' => 'Two people preparing soil in a raised garden bed.',
                        'caption' => null,
                        'recommendation' => 'include',
                        'section_heading' => 'Why gardens matter',
                        'narrative_role' => 'Shows the shared work that brings neighbors together.',
                        'writing_angle' => 'Explain how a practical task can create conversation.',
                        'key_points' => ['Shared work lowers social barriers.', 'The bed gives the group a common focus.'],
                        'transition_in' => 'The benefit becomes clearer in the work itself.',
                        'transition_out' => 'That shared energy can lead into the first practical steps.',
                        'rationale' => 'The moment makes the plan opening concrete.',
                        'questions_for_author' => ['What were the participants preparing to plant?'],
                    ],
                    [
                        'media_id' => $secondMedia->id,
                        'suggested_name' => 'Seedlings ready to plant',
                        'alt_text' => 'Several seedling trays arranged beside a garden bed.',
                        'caption' => 'Seedlings waiting for planting.',
                        'recommendation' => 'include',
                        'section_heading' => 'Getting started',
                        'narrative_role' => 'Introduces the materials needed for a first planting day.',
                        'writing_angle' => 'Give newcomers a concrete picture of simple preparation.',
                        'key_points' => ['Start with a manageable number of plants.', 'Prepare supplies before volunteers arrive.'],
                        'transition_in' => null,
                        'transition_out' => null,
                        'rationale' => 'The image supports the practical second section.',
                        'questions_for_author' => [],
                    ],
                ],
                'group_suggestions' => [[
                    'media_ids' => [$media->id, $secondMedia->id],
                    'section_heading' => 'Getting started',
                    'sequence_rationale' => 'The first image establishes teamwork and the second shows the next concrete step.',
                ]],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Reviewed two images.', 0.01, 800),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Completed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ImageGuidance)->count())->toBe(2)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ImageGroupSuggestion)->count())->toBe(1)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::Plan)->count())->toBe(0)
        ->and($runner->lastRequest?->enabledTools)->not->toContain('WebSearch');
});

test('an image passage job stores exactly three reviewable variants', function (): void {
    $media = PostMedia::factory()->for($this->post)->create();
    Storage::disk('public')->put($media->path, 'image-bytes');
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'base_lock_version' => $this->post->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical guide',
                'angle' => 'Step-by-step',
                'sections' => ['Why gardens matter', 'Getting started'],
                'estimated_length' => 'medium',
                'source_ids' => [$this->source->id],
            ],
        ]);
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($plan, 'plan')
        ->create([
            'section_heading' => 'Why gardens matter',
            'selected_by_id' => $this->user->id,
        ]);
    $composition->media()->attach($media->id, [
        'plan_id' => $plan->id,
        'position' => 1,
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImagePassages($this->post, $composition);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImagePassages,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImagePassages, $context),
    ]);

    runQueuedAuthoringJob($task, [
        function (AgentRequest $request): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'passages' => [
                    [
                        'label' => 'Practical explanation',
                        'body_markdown' => 'On social media: volunteers explained how preparing the soil gave everyone a clear role.',
                    ],
                    [
                        'label' => 'Story-led moment',
                        'body_markdown' => 'Hands moved between the shared tools and the raised bed as the group settled into a rhythm.',
                    ],
                    [
                        'label' => 'Reflective connection',
                        'body_markdown' => 'A garden can begin as a patch of soil and become a reason for neighbors to keep showing up.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Drafted three passage options.', 0.01, 900),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Completed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ImagePassage)->count())->toBe(3)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ImagePassage)->pluck('position')->all())
        ->toBe([1, 2, 3])
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ImagePassage)->firstOrFail()->parent_id)
        ->toBe($plan->id);
});

test('an image passage job rejects invalid or duplicate variants', function (array $passages): void {
    $media = PostMedia::factory()->for($this->post)->create();
    Storage::disk('public')->put($media->path, 'image-bytes');
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'base_lock_version' => $this->post->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical guide',
                'angle' => 'Step-by-step',
                'sections' => ['Why gardens matter', 'Getting started'],
                'estimated_length' => 'medium',
                'source_ids' => [],
            ],
        ]);
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($plan, 'plan')
        ->create([
            'section_heading' => 'Why gardens matter',
            'selected_by_id' => $this->user->id,
        ]);
    $composition->media()->attach($media->id, [
        'plan_id' => $plan->id,
        'position' => 1,
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImagePassages($this->post, $composition);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImagePassages,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImagePassages, $context),
    ]);

    runQueuedAuthoringJob($task, [
        function (AgentRequest $request) use ($passages): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'passages' => $passages,
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Drafted invalid options.', 0.01, 600),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Failed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ImagePassage)->count())->toBe(0);
})->with([
    'wrong option count' => [[
        ['label' => 'First', 'body_markdown' => 'A first option.'],
        ['label' => 'Second', 'body_markdown' => 'A second option.'],
    ]],
    'duplicate option bodies' => [[
        ['label' => 'First', 'body_markdown' => 'The same passage.'],
        ['label' => 'Second', 'body_markdown' => '  The   same passage.  '],
        ['label' => 'Third', 'body_markdown' => 'A genuinely different passage.'],
    ]],
    'four paragraphs' => [[
        ['label' => 'First', 'body_markdown' => "One.\n\nTwo.\n\nThree.\n\nFour."],
        ['label' => 'Second', 'body_markdown' => 'A valid second passage.'],
        ['label' => 'Third', 'body_markdown' => 'A valid third passage.'],
    ]],
    'tilde fenced code' => [[
        ['label' => 'First', 'body_markdown' => "Before.\n\n~~~\ncode\n~~~"],
        ['label' => 'Second', 'body_markdown' => 'A valid second passage.'],
        ['label' => 'Third', 'body_markdown' => 'A valid third passage.'],
    ]],
    'indented code' => [[
        ['label' => 'First', 'body_markdown' => "Before.\n\n    code"],
        ['label' => 'Second', 'body_markdown' => 'A valid second passage.'],
        ['label' => 'Third', 'body_markdown' => 'A valid third passage.'],
    ]],
    'Setext heading' => [[
        ['label' => 'First', 'body_markdown' => "Heading\n-------\n\nParagraph."],
        ['label' => 'Second', 'body_markdown' => 'A valid second passage.'],
        ['label' => 'Third', 'body_markdown' => 'A valid third passage.'],
    ]],
    'source syntax' => [[
        ['label' => 'First', 'body_markdown' => 'A sourced claim.[](source:42)'],
        ['label' => 'Second', 'body_markdown' => 'A valid second passage.'],
        ['label' => 'Third', 'body_markdown' => 'A valid third passage.'],
    ]],
    'reference image' => [[
        ['label' => 'First', 'body_markdown' => "![garden][photo]\n\n[photo]: https://example.com/garden.jpg"],
        ['label' => 'Second', 'body_markdown' => 'A valid second passage.'],
        ['label' => 'Third', 'body_markdown' => 'A valid third passage.'],
    ]],
]);

test('a draft variant is generated lazily for its plan and remains review only', function (): void {
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'base_lock_version' => $this->post->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical guide',
                'angle' => 'Step-by-step',
                'sections' => ['Why gardens matter'],
                'estimated_length' => 'medium',
                'source_ids' => [$this->source->id],
            ],
        ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forDraft($this->post, $plan);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Draft,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Draft, $context),
    ]);
    $originalTitle = $this->post->draft()->firstOrFail()->title;

    runQueuedAuthoringJob($task, [
        function (AgentRequest $request): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'title' => 'A generated variant',
                'excerpt' => 'A concise summary.',
                'body_markdown' => "## Why gardens matter\n\nGardens connect neighbors.[](source:{$this->source->id})",
                'citations' => [[
                    'number' => 1,
                    'source_id' => $this->source->id,
                    'title' => $this->source->title,
                    'url' => null,
                ]],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Drafted a variant.', 0.02, 2200),
    ]);

    $draft = $task->artifacts()->where('type', AuthoringArtifactType::Draft)->sole();

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Completed)
        ->and($draft->parent_id)->toBe($plan->id)
        ->and($draft->payload['title'])->toBe('A generated variant')
        ->and($this->post->draft()->firstOrFail()->title)->toBe($originalTitle)
        ->and(AssistantChange::query()->count())->toBe(0);
});

test('a draft rejects story photo blocks that break the approved contract', function (string $violation): void {
    $firstPhoto = PostMedia::factory()->for($this->post)->create([
        'alt_text' => 'Two neighbors preparing soil together.',
        'caption' => 'Preparing the first community bed.',
    ]);
    $secondPhoto = PostMedia::factory()->for($this->post)->create([
        'alt_text' => 'Seedling trays beside the prepared bed.',
        'caption' => null,
    ]);
    Storage::disk('public')->put($firstPhoto->path, 'first-image-bytes');
    Storage::disk('public')->put($secondPhoto->path, 'second-image-bytes');
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'base_lock_version' => $this->post->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical guide',
                'angle' => 'Step-by-step',
                'sections' => ['Why gardens matter'],
                'estimated_length' => 'medium',
                'source_ids' => [],
            ],
        ]);
    $passage = 'Working on the same garden bed gave new volunteers an easy way to talk.';
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($plan, 'plan')
        ->create([
            'usage' => 'include',
            'section_heading' => 'Why gardens matter',
            'position' => 1,
            'passage_markdown' => $passage,
            'selected_by_id' => $this->user->id,
        ]);
    $composition->media()->attach([
        $firstPhoto->id => ['plan_id' => $plan->id, 'position' => 1],
        $secondPhoto->id => ['plan_id' => $plan->id, 'position' => 2],
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forDraft($this->post, $plan);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Draft,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Draft, $context),
    ]);
    $firstToken = "![Two neighbors preparing soil together.](media:{$firstPhoto->id} \"Preparing the first community bed.\")";
    $secondToken = "![Seedling trays beside the prepared bed.](media:{$secondPhoto->id})";
    $bodyMarkdown = match ($violation) {
        'wrong alt text' => "## Why gardens matter\n\n{$passage}\n\n![A rewritten description.](media:{$firstPhoto->id} \"Preparing the first community bed.\")\n\n{$secondToken}",
        'wrong caption' => "## Why gardens matter\n\n{$passage}\n\n![Two neighbors preparing soil together.](media:{$firstPhoto->id} \"A rewritten caption.\")\n\n{$secondToken}",
        'detached passage' => "## Why gardens matter\n\n{$passage}\n\nAn added paragraph separates the approval from its photos.\n\n{$firstToken}\n\n{$secondToken}",
        'reordered group' => "## Why gardens matter\n\n{$passage}\n\n{$secondToken}\n\n{$firstToken}",
        'prefixed passage' => "## Why gardens matter\n\nNot {$passage}\n\n{$firstToken}\n\n{$secondToken}",
        'no blank line before media' => "## Why gardens matter\n\n{$passage}\n{$firstToken}\n\n{$secondToken}",
        'duplicated approved passage' => "## Why gardens matter\n\n{$passage}\n\n{$firstToken}\n\n{$secondToken}\n\n{$passage}",
    };

    runQueuedAuthoringJob($task, [
        function (AgentRequest $request) use ($bodyMarkdown): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'title' => 'A generated variant',
                'excerpt' => 'A concise summary.',
                'body_markdown' => $bodyMarkdown,
                'citations' => [],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Drafted an invalid variant.', 0.02, 1800),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Failed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::Draft)->count())->toBe(0);
})->with([
    'wrong alt text',
    'wrong caption',
    'detached passage',
    'reordered group',
    'prefixed passage',
    'no blank line before media',
    'duplicated approved passage',
]);

test('a draft accepts duplicate and overlapping passages as ordered canonical photo blocks', function (array $passages): void {
    $firstPhoto = PostMedia::factory()->for($this->post)->create([
        'alt_text' => 'Neighbors filling the first raised bed.',
        'caption' => 'Gardeners called it "the first bed".',
    ]);
    $secondPhoto = PostMedia::factory()->for($this->post)->create([
        'alt_text' => 'Neighbors watering seedlings together.',
        'caption' => null,
    ]);
    Storage::disk('public')->put($firstPhoto->path, 'first-image-bytes');
    Storage::disk('public')->put($secondPhoto->path, 'second-image-bytes');
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'base_lock_version' => $this->post->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical guide',
                'angle' => 'Step-by-step',
                'sections' => ['Why gardens matter'],
                'estimated_length' => 'medium',
                'source_ids' => [],
            ],
        ]);

    foreach ([$firstPhoto, $secondPhoto] as $index => $photo) {
        $composition = AuthoringImageComposition::factory()
            ->for($this->post, 'post')
            ->for($plan, 'plan')
            ->create([
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'position' => $index + 1,
                'passage_markdown' => $passages[$index],
                'selected_by_id' => $this->user->id,
            ]);
        $composition->media()->attach($photo->id, [
            'plan_id' => $plan->id,
            'position' => 1,
        ]);
    }

    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forDraft($this->post, $plan);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Draft,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Draft, $context),
    ]);
    $bodyMarkdown = "## Why gardens matter\n\n{$passages[0]}\n\n![Neighbors filling the first raised bed.](media:{$firstPhoto->id} \"Gardeners called it \\\"the first bed\\\".\")\n\nThe work continued at the next plot.\n\n{$passages[1]}\n\n![Neighbors watering seedlings together.](media:{$secondPhoto->id})";

    $runner = runQueuedAuthoringJob($task, [
        function (AgentRequest $request) use ($bodyMarkdown): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'title' => 'A generated variant',
                'excerpt' => 'A concise summary.',
                'body_markdown' => $bodyMarkdown,
                'citations' => [],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Drafted a valid variant.', 0.02, 1900),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Completed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::Draft)->count())->toBe(1)
        ->and($runner->lastRequest?->prompt)
        ->toContain('ALT must equal that media item\'s approved alt_text exactly')
        ->toContain('emit that composition\'s media tokens contiguously in media array order');
})->with([
    'identical approved passages' => [[
        'Each shared task gave neighbors a natural reason to start a conversation.',
        'Each shared task gave neighbors a natural reason to start a conversation.',
    ]],
    'overlapping approved passages' => [[
        'The garden grew.',
        'The garden grew. Neighbors joined in.',
    ]],
]);

test('research has web search only while the persisted opt in remains enabled', function (): void {
    $this->journey->forceFill(['web_research_enabled' => true])->save();
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forResearch($this->post);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Research,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Research, $context),
    ]);

    $runner = runQueuedAuthoringJob($task, [
        function (AgentRequest $request): void {
            file_put_contents($request->workspacePath.'/authoring-output.json', json_encode([
                'findings' => [[
                    'title' => 'Community garden research',
                    'summary' => 'A useful primary finding.',
                    'url' => 'https://example.org/research',
                    'publisher' => 'Example Institute',
                    'published_at' => '2026-01-02',
                    'suggested_citation' => 'Example Institute (2026)',
                ]],
            ], JSON_THROW_ON_ERROR));
        },
        AgentEvent::result(false, 'Found one source.', 0.01, 900),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Completed)
        ->and($task->artifacts()->where('type', AuthoringArtifactType::ResearchFinding)->count())->toBe(1)
        ->and($runner->lastRequest?->enabledTools)->toContain('WebSearch')
        ->and($runner->lastRequest?->disallowedTools)->not->toContain('WebSearch');
});

test('a queued research task is stopped if the author revokes web research', function (): void {
    $this->journey->forceFill(['web_research_enabled' => true])->save();
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forResearch($this->post);
    $task = AuthoringTask::factory()->queued()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Research,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Research, $context),
    ]);
    $this->journey->forceFill(['web_research_enabled' => false])->save();

    $runner = runQueuedAuthoringJob($task, [
        AgentEvent::result(false, 'This must not run.', null, 10),
    ]);

    expect($task->refresh()->status)->toBe(AuthoringTaskStatus::Failed)
        ->and($task->error_code)->toBe('web_research_disabled')
        ->and($runner->lastRequest)->toBeNull()
        ->and(AuthoringArtifact::query()->count())->toBe(0);
});

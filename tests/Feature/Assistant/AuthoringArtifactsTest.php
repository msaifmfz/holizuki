<?php

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Jobs\RunAuthoringTask;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Prompts\AuthoringDraftPrompt;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Live projection title',
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Original working body.']],
            ]],
        ],
    ]);
    $this->journey = PostAuthoringJourney::factory()->for($this->post, 'post')->create([
        'brief' => [
            'topic' => 'How community gardens strengthen neighborhoods',
            'audience' => 'First-time volunteers',
            'purpose' => 'Explain how to get started',
        ],
    ]);
});

/**
 * @param  array<string, mixed>  $payload
 */
function createCurrentStoryPhotoPlan(
    Post $post,
    User $user,
    array $payload = [],
): AuthoringArtifact {
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forPlans($post);
    $task = AuthoringTask::factory()->for($post, 'post')->create([
        'requested_by_id' => $user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $context),
    ]);

    return AuthoringArtifact::factory()
        ->for($post, 'post')
        ->for($task, 'task')
        ->create([
            'base_lock_version' => $post->lock_version,
            'payload' => [
                'title' => 'A practical community garden guide',
                'angle' => 'A welcoming step-by-step introduction',
                'sections' => ['Why gardens matter', 'Getting started'],
                'estimated_length' => 'medium',
                'source_ids' => [],
                ...$payload,
            ],
        ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function createCurrentStoryPhotoDraft(
    Post $post,
    User $user,
    AuthoringArtifact $plan,
    array $payload = [],
): AuthoringArtifact {
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forDraft($post, $plan);
    $task = AuthoringTask::factory()->for($post, 'post')->create([
        'requested_by_id' => $user->id,
        'kind' => AuthoringTaskKind::Draft,
        'base_lock_version' => $post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Draft, $context),
    ]);

    return AuthoringArtifact::factory()
        ->draft()
        ->for($post, 'post')
        ->for($task, 'task')
        ->for($plan, 'parent')
        ->create([
            'group_id' => (string) Str::uuid(),
            'base_lock_version' => $post->lock_version,
            'payload' => [
                'title' => 'A reviewed working title',
                'excerpt' => 'A reviewed excerpt.',
                'body_markdown' => "## Why gardens matter\n\nA complete reviewed draft.\n\n## Getting started\n\nBegin with one small step.",
                'citations' => [],
                ...$payload,
            ],
        ]);
}

test('guests cannot read guided authoring artifacts', function (): void {
    $this->get(route('posts.assistant.authoring.index', $this->post))
        ->assertRedirect(route('login'));
});

test('plan generation validates the saved brief', function (): void {
    $this->journey->forceFill(['brief' => []])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.store', $this->post))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('brief.topic');
});

test('plan generation is queued once and cached idempotently', function (): void {
    Queue::fake();
    $requestId = (string) Str::uuid();

    $first = $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.store', $this->post), [
            'request_id' => $requestId,
        ])
        ->assertAccepted()
        ->assertJsonPath('task.kind', 'plans')
        ->assertJsonPath('task.status', 'queued')
        ->assertJsonPath('cached', false)
        ->assertJsonPath('resumed', false);

    $taskId = $first->json('task.id');

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.store', $this->post), [
            'request_id' => $requestId,
        ])
        ->assertAccepted()
        ->assertJsonPath('task.id', $taskId)
        ->assertJsonPath('cached', true);

    Queue::assertPushed(
        RunAuthoringTask::class,
        fn (RunAuthoringTask $job): bool => $job->postId === $this->post->id,
    );
    Queue::assertPushedTimes(RunAuthoringTask::class, 1);
    expect(AuthoringTask::query()->count())->toBe(1);
});

test('image guidance requires the selected plan', function (): void {
    Queue::fake();
    PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);

    $this->actingAs($this->user)
        ->postJson(
            route('posts.assistant.authoring.image-guidance.store', [$this->post, $plan]),
        )
        ->assertUnprocessable();

    Queue::assertNothingPushed();
});

test('image guidance uses its own queued task for a selected plan', function (): void {
    Queue::fake();
    PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(
            route('posts.assistant.authoring.image-guidance.store', [$this->post, $plan]),
        )
        ->assertAccepted()
        ->assertJsonPath('task.kind', 'image_guidance')
        ->assertJsonPath('task.status', 'queued');

    Queue::assertPushed(RunAuthoringTask::class);
    expect(
        AuthoringTask::query()
            ->where('kind', AuthoringTaskKind::ImageGuidance)
            ->sole()
            ->kind,
    )->toBe(AuthoringTaskKind::ImageGuidance);
});

test('image guidance requires an uploaded non-featured story photo', function (): void {
    Queue::fake();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(
            route('posts.assistant.authoring.image-guidance.store', [$this->post, $plan]),
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images');

    Queue::assertNothingPushed();
});

test('photo passages exclude guidance that predates changed author notes', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    $contexts = resolve(AuthoringContextFactory::class);
    $guidanceContext = $contexts->forImageGuidance($this->post, $plan);
    $guidanceTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $guidanceContext,
        'input_hash' => $contexts->hash(
            AuthoringTaskKind::ImageGuidance,
            $guidanceContext,
        ),
    ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($guidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGuidance,
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'media_id' => $photo->id,
                'writing_angle' => 'Use the original author context.',
            ],
        ]);
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($plan, 'plan')
        ->create(['section_heading' => 'Why gardens matter']);
    $composition->media()->attach($photo->id, [
        'plan_id' => $plan->id,
        'position' => 1,
    ]);

    expect($contexts->forImagePassages($this->post, $composition)['image_guidance'])
        ->toHaveCount(1);

    $photo->forceFill(['story_notes' => 'The author corrected the story context.'])->save();

    expect($contexts->forImagePassages($this->post, $composition->refresh())['image_guidance'])
        ->toBe([]);
});

test('story photo flow requires required photos and rejects duplicate assignments', function (): void {
    $requiredPhoto = PostMedia::factory()->for($this->post)->create([
        'story_priority' => PostMediaStoryPriority::Required,
    ]);
    $optionalPhoto = PostMedia::factory()->for($this->post)->create([
        'story_priority' => PostMediaStoryPriority::Optional,
    ]);
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'usage' => 'exclude',
                    'section_heading' => null,
                    'media_ids' => [$requiredPhoto->id],
                ],
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$optionalPhoto->id],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$requiredPhoto->id],
                ],
                [
                    'usage' => 'exclude',
                    'section_heading' => null,
                    'media_ids' => [$requiredPhoto->id],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.1.media_ids');

    expect(AuthoringImageComposition::query()->count())->toBe(0);

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$requiredPhoto->id],
                ],
                [
                    'usage' => 'exclude',
                    'section_heading' => null,
                    'media_ids' => [$optionalPhoto->id],
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'image_compositions')
        ->assertJsonPath('image_compositions.0.media.0.id', $requiredPhoto->id)
        ->assertJsonPath('image_compositions.1.usage', 'exclude');
});

test('story photo flow rejects associative block and media ID objects', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $block = [
        'usage' => 'include',
        'section_heading' => 'Why gardens matter',
        'media_ids' => [$photo->id],
    ];

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => ['first' => $block],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                ...$block,
                'media_ids' => ['photo' => $photo->id],
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.0.media_ids');
});

test('story photo flow only accepts a current AI-suggested multi-photo group', function (): void {
    $firstPhoto = PostMedia::factory()->for($this->post)->create();
    $secondPhoto = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.0.media_ids');

    expect(AuthoringImageComposition::query()->count())->toBe(0);

    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImageGuidance($this->post, $plan);
    $guidanceTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImageGuidance, $context),
    ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($guidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGuidance,
            'group_id' => $guidanceTask->public_id,
            'base_lock_version' => $this->post->lock_version,
            'payload' => ['media_id' => $firstPhoto->id],
        ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($guidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGroupSuggestion,
            'group_id' => $guidanceTask->public_id,
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
                'section_heading' => 'Why gardens matter',
                'sequence_rationale' => 'The sequence moves from preparation to planting.',
            ],
        ]);

    $savedGroup = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'image_compositions')
        ->assertJsonCount(2, 'image_compositions.0.media');
    $savedGroupId = $savedGroup->json('image_compositions.0.id');

    $newerGuidanceTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImageGuidance, $context),
    ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($newerGuidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGuidance,
            'group_id' => $newerGuidanceTask->public_id,
            'base_lock_version' => $this->post->lock_version,
            'payload' => ['media_id' => $firstPhoto->id],
        ]);

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'id' => $savedGroupId,
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
            ]],
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.0.media_ids');
});

test('story photo regrouping cannot discard saved author passages', function (): void {
    $firstPhoto = PostMedia::factory()->for($this->post)->create();
    $secondPhoto = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $flow = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$firstPhoto->id],
                ],
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$secondPhoto->id],
                ],
            ],
        ])
        ->assertSuccessful();
    $firstCompositionId = $flow->json('image_compositions.0.id');

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $firstCompositionId,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => 'The author wants to keep this passage.',
            ],
        )
        ->assertSuccessful();

    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImageGuidance($this->post, $plan);
    $guidanceTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImageGuidance, $context),
    ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($guidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGuidance,
            'group_id' => $guidanceTask->public_id,
            'base_lock_version' => $this->post->lock_version,
            'payload' => ['media_id' => $firstPhoto->id],
        ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($guidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGroupSuggestion,
            'group_id' => $guidanceTask->public_id,
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
                'section_heading' => 'Why gardens matter',
            ],
        ]);

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$firstPhoto->id, $secondPhoto->id],
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');

    expect(
        AuthoringImageComposition::query()
            ->where('public_id', $firstCompositionId)
            ->value('passage_markdown'),
    )->toBe('The author wants to keep this passage.');
});

test('story photo flow rejects duplicated or foreign composition identifiers', function (): void {
    $firstPhoto = PostMedia::factory()->for($this->post)->create();
    $secondPhoto = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$firstPhoto->id],
                ],
                [
                    'usage' => 'include',
                    'section_heading' => 'Getting started',
                    'media_ids' => [$secondPhoto->id],
                ],
            ],
        ])
        ->assertSuccessful();
    $compositionId = $response->json('image_compositions.0.id');

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'id' => $compositionId,
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$firstPhoto->id],
                ],
                [
                    'id' => $compositionId,
                    'usage' => 'include',
                    'section_heading' => 'Getting started',
                    'media_ids' => [$secondPhoto->id],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.1.id');

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'id' => (string) Str::uuid(),
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$firstPhoto->id],
                ],
                [
                    'usage' => 'include',
                    'section_heading' => 'Getting started',
                    'media_ids' => [$secondPhoto->id],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.0.id');
});

test('reordering photo blocks preserves passages for explicit review', function (): void {
    $firstPhoto = PostMedia::factory()->for($this->post)->create();
    $secondPhoto = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $flow = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$firstPhoto->id],
                ],
                [
                    'usage' => 'include',
                    'section_heading' => 'Getting started',
                    'media_ids' => [$secondPhoto->id],
                ],
            ],
        ])
        ->assertSuccessful();
    $firstCompositionId = $flow->json('image_compositions.0.id');
    $secondCompositionId = $flow->json('image_compositions.1.id');

    foreach ([$firstCompositionId, $secondCompositionId] as $index => $compositionId) {
        $this->actingAs($this->user)
            ->patchJson(
                route('posts.assistant.authoring.image-compositions.update', [
                    $this->post,
                    $compositionId,
                ]),
                [
                    'selected_passage_id' => null,
                    'passage_markdown' => 'Approved passage '.($index + 1).'.',
                ],
            )
            ->assertSuccessful();
    }

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'id' => $secondCompositionId,
                    'usage' => 'include',
                    'section_heading' => 'Getting started',
                    'media_ids' => [$secondPhoto->id],
                ],
                [
                    'id' => $firstCompositionId,
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$firstPhoto->id],
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('image_compositions.0.passage_markdown', 'Approved passage 2.')
        ->assertJsonPath('image_compositions.0.passage_stale', true)
        ->assertJsonPath('image_compositions.1.passage_markdown', 'Approved passage 1.')
        ->assertJsonPath('image_compositions.1.passage_stale', true);
});

test('draft context hides a recovered passage while its photo composition is excluded', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    $recoveredPassage = 'Recovery-only passage that must not be sent to the drafting assistant.';
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($plan, 'plan')
        ->create([
            'usage' => 'exclude',
            'section_heading' => null,
            'passage_markdown' => $recoveredPassage,
            'selected_by_id' => $this->user->id,
        ]);
    $composition->media()->attach($photo->id, [
        'plan_id' => $plan->id,
        'position' => 1,
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forDraft($this->post, $plan);
    $flowComposition = collect($context['photo_flow'])
        ->firstWhere('id', $composition->public_id);
    $task = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Draft,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Draft, $context),
    ]);
    $prompt = resolve(AuthoringDraftPrompt::class)->build($task, $plan);

    expect($composition->refresh()->passage_markdown)->toBe($recoveredPassage)
        ->and($flowComposition)->toBeArray()
        ->and($flowComposition)->not->toHaveKey('passage_markdown')
        ->and($prompt)->not->toContain($recoveredPassage);
});

test('excluded photo passages remain recoverable in flow and hydration responses', function (): void {
    $photo = PostMedia::factory()->libraryUpload()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $flow = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful();
    $compositionId = $flow->json('image_compositions.0.id');
    $recoveredPassage = 'Keep this passage available if the author restores the photo.';

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $compositionId,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => $recoveredPassage,
            ],
        )
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'id' => $compositionId,
                'usage' => 'exclude',
                'section_heading' => null,
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('image_compositions.0.usage', 'exclude')
        ->assertJsonPath('image_compositions.0.passage_markdown', $recoveredPassage)
        ->assertJsonPath('image_compositions.0.passage_stale', false);

    expect(
        AuthoringImageComposition::query()
            ->where('public_id', $compositionId)
            ->value('passage_markdown'),
    )->toBe($recoveredPassage);

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.index', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('image_compositions.0.usage', 'exclude')
        ->assertJsonPath('image_compositions.0.passage_markdown', $recoveredPassage)
        ->assertJsonPath('image_compositions.0.passage_stale', false);
});

test('authors can write and save their own passage for an included photo', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $flowResponse = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful();
    $compositionId = $flowResponse->json('image_compositions.0.id');

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $compositionId,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => 'On social media: neighbors shared how this raised bed became a place to learn.',
            ],
        )
        ->assertSuccessful()
        ->assertJsonPath('image_composition.selected_passage_id', null)
        ->assertJsonPath(
            'image_composition.passage_markdown',
            'On social media: neighbors shared how this raised bed became a place to learn.',
        );

    $composition = AuthoringImageComposition::query()->sole();

    expect($composition->selected_passage_id)->toBeNull()
        ->and($composition->passage_markdown)
        ->toBe('On social media: neighbors shared how this raised bed became a place to learn.')
        ->and($composition->passage_input_hash)
        ->toBeString()
        ->toHaveLength(64);
});

test('stale AI passages require an explicit manual reapproval after photo context changes', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $flowResponse = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful();
    $composition = AuthoringImageComposition::query()
        ->where('public_id', $flowResponse->json('image_compositions.0.id'))
        ->sole();
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImagePassages($this->post, $composition);
    $task = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImagePassages,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImagePassages, $context),
    ]);
    $passage = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($task, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImagePassage,
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'composition_id' => $composition->public_id,
                'label' => 'Reflective',
                'body_markdown' => 'The first approved AI passage.',
            ],
        ]);

    $route = route('posts.assistant.authoring.image-compositions.update', [
        $this->post,
        $composition,
    ]);

    $this->actingAs($this->user)
        ->patchJson($route, [
            'selected_passage_id' => $plan->public_id,
            'passage_markdown' => 'A plan is not a valid passage.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected_passage_id');

    $this->actingAs($this->user)
        ->patchJson($route, [
            'selected_passage_id' => $passage->public_id,
            'passage_markdown' => 'The first approved AI passage.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('image_composition.passage_stale', false);

    $photo->forceFill(['story_notes' => 'The author added newer context.'])->save();

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.index', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('image_compositions.0.passage_stale', true);

    $this->actingAs($this->user)
        ->patchJson($route, [
            'selected_passage_id' => $passage->public_id,
            'passage_markdown' => 'The first approved AI passage.',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'stale_authoring_artifact');

    $this->actingAs($this->user)
        ->patchJson($route, [
            'selected_passage_id' => null,
            'passage_markdown' => 'The first approved AI passage.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('image_composition.passage_stale', false);
});

test('authors can request a genuinely new passage set for unchanged photo context', function (): void {
    Queue::fake();
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $flowResponse = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful();
    $route = route('posts.assistant.authoring.image-passages.store', [
        $this->post,
        $flowResponse->json('image_compositions.0.id'),
    ]);

    $firstResponse = $this->actingAs($this->user)
        ->postJson($route, ['request_id' => (string) Str::uuid()])
        ->assertAccepted()
        ->assertJsonPath('task.target_id', $flowResponse->json('image_compositions.0.id'))
        ->assertJsonPath('cached', false);
    $firstTask = AuthoringTask::query()
        ->where('public_id', $firstResponse->json('task.id'))
        ->sole();
    $firstTask->forceFill([
        'status' => AuthoringTaskStatus::Completed,
        'finished_at' => now(),
    ])->save();

    $secondResponse = $this->actingAs($this->user)
        ->postJson($route, ['request_id' => (string) Str::uuid()])
        ->assertAccepted()
        ->assertJsonPath('cached', false);

    expect($secondResponse->json('task.id'))->not->toBe($firstTask->public_id)
        ->and(AuthoringTask::query()
            ->where('kind', AuthoringTaskKind::ImagePassages)
            ->count())
        ->toBe(2);
    Queue::assertPushedTimes(RunAuthoringTask::class, 2);
});

test('draft generation waits until every photo is decided and each included block has a passage', function (): void {
    Queue::fake();
    $includedPhoto = PostMedia::factory()->for($this->post)->create([
        'story_priority' => PostMediaStoryPriority::Required,
    ]);
    $optionalPhoto = PostMedia::factory()->for($this->post)->create([
        'story_priority' => PostMediaStoryPriority::Optional,
    ]);
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$includedPhoto->id],
            ]],
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.store', [$this->post, $plan]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo_flow');

    $composition = AuthoringImageComposition::query()->sole();

    $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [
                [
                    'id' => $composition->public_id,
                    'usage' => 'include',
                    'section_heading' => 'Why gardens matter',
                    'media_ids' => [$includedPhoto->id],
                ],
                [
                    'usage' => 'exclude',
                    'section_heading' => null,
                    'media_ids' => [$optionalPhoto->id],
                ],
            ],
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.store', [$this->post, $plan]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo_flow');

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $composition,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => 'The garden gives neighbors a practical reason to meet.',
            ],
        )
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.store', [$this->post, $plan]))
        ->assertAccepted()
        ->assertJsonPath('task.kind', 'draft');

    Queue::assertPushed(RunAuthoringTask::class);
});

test('a photo passage must be reviewed again after its author context changes', function (): void {
    Queue::fake();
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $flowResponse = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful();
    $compositionId = $flowResponse->json('image_compositions.0.id');
    $passage = 'The volunteers prepared this bed together before planting.';

    $this->actingAs($this->user)
        ->patchJson(route('posts.assistant.authoring.image-compositions.update', [
            $this->post,
            $compositionId,
        ]), [
            'selected_passage_id' => null,
            'passage_markdown' => $passage,
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $photo]), [
            'story_notes' => 'This was the first bed prepared during the spring workshop.',
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.store', [$this->post, $plan]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo_flow');

    $this->actingAs($this->user)
        ->patchJson(route('posts.assistant.authoring.image-compositions.update', [
            $this->post,
            $compositionId,
        ]), [
            'selected_passage_id' => null,
            'passage_markdown' => $passage,
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.store', [$this->post, $plan]))
        ->assertAccepted();

    Queue::assertPushed(RunAuthoringTask::class);
});

test('changing an approved photo passage makes an existing draft stale', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);

    $flowResponse = $this->actingAs($this->user)
        ->putJson(route('posts.assistant.authoring.image-flow.update', [$this->post, $plan]), [
            'blocks' => [[
                'usage' => 'include',
                'section_heading' => 'Why gardens matter',
                'media_ids' => [$photo->id],
            ]],
        ])
        ->assertSuccessful();
    $composition = AuthoringImageComposition::query()
        ->where('public_id', $flowResponse->json('image_compositions.0.id'))
        ->sole();
    $composition->forceFill([
        'passage_markdown' => 'The first approved passage.',
        'selected_by_id' => $this->user->id,
    ])->save();
    $draft = createCurrentStoryPhotoDraft($this->post, $this->user, $plan, [
        'body_markdown' => "## Why gardens matter\n\nThe first approved passage.\n\n![Neighbors planting](media:{$photo->id})\n\n## Getting started\n\nBegin with one small step.",
    ]);

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.drafts.show', [$this->post, $draft]))
        ->assertSuccessful()
        ->assertJsonPath('draft.stale', false);

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $composition,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => 'The author has now approved a different passage.',
            ],
        )
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.drafts.show', [$this->post, $draft]))
        ->assertSuccessful()
        ->assertJsonPath('draft.stale', true);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.select', [$this->post, $draft]))
        ->assertConflict()
        ->assertJsonPath('code', 'stale_authoring_artifact');
});

test('a failed task is resumed idempotently instead of being duplicated', function (): void {
    Queue::fake();
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forPlans($this->post);
    $requestId = (string) Str::uuid();
    $task = AuthoringTask::factory()->failed()->for($this->post, 'post')->create([
        'request_id' => $requestId,
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $this->post->lock_version,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $context),
        'context' => $context,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.store', $this->post), [
            'request_id' => $requestId,
        ])
        ->assertAccepted()
        ->assertJsonPath('task.id', $task->public_id)
        ->assertJsonPath('task.status', 'queued')
        ->assertJsonPath('cached', false)
        ->assertJsonPath('resumed', true);

    Queue::assertPushedTimes(RunAuthoringTask::class, 1);
    expect(AuthoringTask::query()->count())->toBe(1)
        ->and($task->refresh()->error_code)->toBeNull()
        ->and($task->finished_at)->toBeNull();
});

test('only one heavy authoring task may be active for a post', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.store', $this->post))
        ->assertAccepted();

    $this->journey->forceFill([
        'brief' => ['topic' => 'A materially different topic'],
    ])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.store', $this->post))
        ->assertConflict()
        ->assertJsonPath('code', 'authoring_task_busy');
});

test('research requires the persisted per-post opt in', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.research.store', $this->post))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('web_research_enabled');

    $this->journey->forceFill(['web_research_enabled' => true])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.research.store', $this->post))
        ->assertAccepted()
        ->assertJsonPath('task.kind', 'research');

    Queue::assertPushed(RunAuthoringTask::class);
});

test('selecting review artifacts never changes the working draft', function (): void {
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    $draft = createCurrentStoryPhotoDraft($this->post, $this->user, $plan);
    $originalTitle = $this->post->draft()->firstOrFail()->title;

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.select', [$this->post, $plan]))
        ->assertSuccessful()
        ->assertJsonPath('selection.plan_id', $plan->public_id)
        ->assertJsonPath('selection.draft_id', null);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.select', [$this->post, $draft]))
        ->assertSuccessful()
        ->assertJsonPath('selection.plan_id', $plan->public_id)
        ->assertJsonPath('selection.draft_id', $draft->public_id);

    expect($this->post->draft()->firstOrFail()->title)->toBe($originalTitle)
        ->and($this->post->refresh()->lock_version)->toBe(0);
});

test('an explicit reviewed apply writes only the private working draft', function (): void {
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $draft = createCurrentStoryPhotoDraft($this->post, $this->user, $plan);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.drafts.apply', [$this->post, $draft]), [
            'lock_version' => $this->post->lock_version,
        ])
        ->assertSuccessful()
        ->assertJsonPath('post.lock_version', 1)
        ->assertJsonPath('post.title', 'A reviewed working title')
        ->assertJsonPath('post.body.type', 'doc')
        ->assertJsonPath('applied_artifact_id', $draft->public_id)
        ->assertJsonPath('selection.draft_id', $draft->public_id);

    $post = $this->post->refresh();
    $workingDraft = $post->draft()->firstOrFail();

    expect($post->title)->toBe('Live projection title')
        ->and($workingDraft->title)->toBe('A reviewed working title')
        ->and($workingDraft->excerpt)->toBe('A reviewed excerpt.')
        ->and($workingDraft->body?->plainText())->toContain('A complete reviewed draft.')
        ->and(AuthoringSelection::query()->where('post_id', $post->id)->exists())->toBeTrue();
});

test('stale artifacts cannot be selected or applied', function (): void {
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    $this->post->forceFill(['lock_version' => 1])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.select', [$this->post, $plan]))
        ->assertConflict()
        ->assertJsonPath('code', 'stale_authoring_artifact');
});

test('artifact payloads are immutable and the index exposes review state', function (): void {
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    $newerPlan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($plan->task()->firstOrFail(), 'task')
        ->create(['base_lock_version' => $this->post->lock_version]);

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.index', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('plans.0.id', $newerPlan->public_id)
        ->assertJsonPath('plans.0.stale', false)
        ->assertJsonPath('plans.1.id', $plan->public_id)
        ->assertJsonPath('selection.plan_id', null);

    expect(function () use ($plan): void {
        $plan->payload = ['title' => 'Mutated'];
        $plan->save();
    })->toThrow(LogicException::class);
});

test('artifact index keeps selected photo work after more than 200 newer artifacts', function (): void {
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $mediaItems = PostMedia::factory()->count(20)->for($this->post)->create();
    $contexts = resolve(AuthoringContextFactory::class);
    $guidanceContext = $contexts->forImageGuidance($this->post, $plan);
    $guidanceTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $guidanceContext,
        'input_hash' => $contexts->hash(
            AuthoringTaskKind::ImageGuidance,
            $guidanceContext,
        ),
    ]);
    $guidanceIds = [];

    foreach ($mediaItems as $position => $media) {
        $guidance = AuthoringArtifact::factory()
            ->for($this->post, 'post')
            ->for($guidanceTask, 'task')
            ->for($plan, 'parent')
            ->create([
                'type' => AuthoringArtifactType::ImageGuidance,
                'group_id' => $guidanceTask->public_id,
                'position' => $position + 1,
                'base_lock_version' => $this->post->lock_version,
                'payload' => [
                    'media_id' => $media->id,
                    'recommendation' => 'include',
                    'section_heading' => 'Why gardens matter',
                ],
            ]);
        $guidanceIds[] = $guidance->public_id;
    }

    $groupSuggestion = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($guidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGroupSuggestion,
            'group_id' => $guidanceTask->public_id,
            'position' => 1,
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'media_ids' => $mediaItems->take(2)->modelKeys(),
                'section_heading' => 'Why gardens matter',
                'sequence_rationale' => 'These two photos form one sequence.',
            ],
        ]);
    $compositions = [];

    foreach ($mediaItems as $position => $media) {
        $composition = AuthoringImageComposition::factory()
            ->for($this->post, 'post')
            ->for($plan, 'plan')
            ->create([
                'selected_by_id' => $this->user->id,
                'section_heading' => 'Why gardens matter',
                'position' => $position + 1,
            ]);
        $composition->media()->attach($media->id, [
            'plan_id' => $plan->id,
            'position' => 1,
        ]);
        $compositions[] = $composition;
    }

    $createPassageGroup = function (
        AuthoringImageComposition $composition,
        string $label,
    ) use ($contexts, $plan): array {
        $context = $contexts->forImagePassages($this->post, $composition);
        $task = AuthoringTask::factory()->for($this->post, 'post')->create([
            'requested_by_id' => $this->user->id,
            'kind' => AuthoringTaskKind::ImagePassages,
            'base_lock_version' => $this->post->lock_version,
            'context' => $context,
            'input_hash' => $contexts->hash(AuthoringTaskKind::ImagePassages, $context),
        ]);
        $artifacts = [];

        foreach (range(1, 3) as $position) {
            $artifacts[] = AuthoringArtifact::factory()
                ->for($this->post, 'post')
                ->for($task, 'task')
                ->for($plan, 'parent')
                ->create([
                    'type' => AuthoringArtifactType::ImagePassage,
                    'group_id' => $task->public_id,
                    'position' => $position,
                    'base_lock_version' => $this->post->lock_version,
                    'payload' => [
                        'composition_id' => $composition->public_id,
                        'label' => "{$label} {$position}",
                        'body_markdown' => "{$label} passage {$position}.",
                    ],
                ]);
        }

        return $artifacts;
    };
    $supersededPassages = $createPassageGroup($compositions[0], 'Superseded');
    $latestPassageIds = [];
    $firstCompositionPassageIds = [];

    foreach ($compositions as $compositionIndex => $composition) {
        foreach ($createPassageGroup($composition, 'Current') as $passage) {
            $latestPassageIds[] = $passage->public_id;

            if ($compositionIndex === 0) {
                $firstCompositionPassageIds[] = $passage->public_id;
            }
        }
    }

    $latestRepeatedPassages = [];

    foreach (range(1, 70) as $run) {
        $latestRepeatedPassages = $createPassageGroup(
            $compositions[0],
            "Repeated run {$run}",
        );
    }

    $latestPassageIds = [
        ...array_values(array_diff($latestPassageIds, $firstCompositionPassageIds)),
        ...array_map(
            fn (AuthoringArtifact $passage): string => $passage->public_id,
            $latestRepeatedPassages,
        ),
    ];
    $planTask = $plan->task()->firstOrFail();

    foreach (range(1, 11) as $group) {
        $groupId = (string) Str::uuid();

        foreach (range(1, 3) as $position) {
            AuthoringArtifact::factory()
                ->for($this->post, 'post')
                ->for($planTask, 'task')
                ->create([
                    'type' => AuthoringArtifactType::Plan,
                    'group_id' => $groupId,
                    'position' => $position,
                    'base_lock_version' => $this->post->lock_version,
                ]);
        }
    }

    $researchContext = $contexts->forResearch($this->post);
    $noiseTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Research,
        'base_lock_version' => $this->post->lock_version,
        'context' => $researchContext,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Research, $researchContext),
    ]);
    AuthoringArtifact::factory()
        ->count(70)
        ->for($this->post, 'post')
        ->for($noiseTask, 'task')
        ->create([
            'type' => AuthoringArtifactType::ResearchFinding,
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'Newer unrelated finding',
                'summary' => 'This should not evict selected-plan artifacts.',
                'url' => 'https://example.com/finding',
                'publisher' => 'Example',
                'published_at' => null,
                'suggested_citation' => 'Example',
            ],
        ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.index', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('selection.plan_id', $plan->public_id)
        ->assertJsonCount(31, 'plans')
        ->assertJsonCount(20, 'image_guidance')
        ->assertJsonCount(1, 'image_group_suggestions')
        ->assertJsonCount(60, 'image_passages')
        ->assertJsonCount(20, 'image_compositions')
        ->assertJsonCount(60, 'research_findings');
    $returnedPlanIds = collect($response->json('plans'))->pluck('id');
    $returnedGuidanceIds = collect($response->json('image_guidance'))->pluck('id');
    $returnedPassageIds = collect($response->json('image_passages'))->pluck('id');

    expect($returnedPlanIds)->toContain($plan->public_id)
        ->and($returnedGuidanceIds->all())->toEqualCanonicalizing($guidanceIds)
        ->and($response->json('image_group_suggestions.0.id'))
        ->toBe($groupSuggestion->public_id)
        ->and($returnedPassageIds->all())
        ->toEqualCanonicalizing($latestPassageIds)
        ->and($returnedPassageIds)
        ->not->toContain($supersededPassages[0]->public_id);
});

test('task polling uses full context freshness for returned artifacts', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $plan = createCurrentStoryPhotoPlan($this->post, $this->user);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImageGuidance($this->post, $plan);
    $task = AuthoringTask::factory()->for($this->post, 'post')->create([
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $this->post->lock_version,
        'context' => $context,
        'input_hash' => $contexts->hash(AuthoringTaskKind::ImageGuidance, $context),
    ]);
    AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($task, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGuidance,
            'group_id' => $task->public_id,
            'base_lock_version' => $this->post->lock_version,
            'payload' => ['media_id' => $photo->id],
        ]);

    $photo->forceFill(['story_notes' => 'The author corrected this context.'])->save();

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.tasks.show', [$this->post, $task]))
        ->assertSuccessful()
        ->assertJsonPath('task.target_id', $plan->public_id)
        ->assertJsonPath('artifacts.0.stale', true);
});

test('artifacts from another post are hidden', function (): void {
    $otherPost = Post::factory()->create();
    $task = AuthoringTask::factory()->for($otherPost, 'post')->create([
        'base_lock_version' => $otherPost->lock_version,
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($otherPost, 'post')
        ->for($task, 'task')
        ->create(['base_lock_version' => $otherPost->lock_version]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.authoring.plans.select', [$this->post, $plan]))
        ->assertNotFound();
});

test('artifact index query count does not scale with staleness checks', function (): void {
    $buildPost = function (int $compositionCount): int {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();
        PostAuthoringJourney::factory()->for($post, 'post')->create([
            'brief' => ['topic' => 'Community gardens'],
        ]);
        $plan = createCurrentStoryPhotoPlan($post, $user);
        AuthoringSelection::factory()->for($post)->create([
            'selected_plan_id' => $plan->id,
            'selected_by_id' => $user->id,
        ]);

        $contexts = resolve(AuthoringContextFactory::class);
        $planContext = $contexts->forPlans($post);

        // Several plan artifacts from distinct tasks: each one is a separate
        // staleness check, and each used to rebuild the whole plan context.
        foreach (range(1, 5) as $index) {
            $task = AuthoringTask::factory()->for($post, 'post')->create([
                'requested_by_id' => $user->id,
                'kind' => AuthoringTaskKind::Plans,
                'base_lock_version' => $post->lock_version,
                'context' => $planContext,
                'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $planContext),
            ]);
            AuthoringArtifact::factory()
                ->for($post, 'post')
                ->for($task, 'task')
                ->create([
                    'type' => AuthoringArtifactType::Plan,
                    'group_id' => $task->public_id,
                    'position' => $index,
                    'base_lock_version' => $post->lock_version,
                    'payload' => ['sections' => ['Why gardens matter']],
                ]);
        }

        foreach (range(1, $compositionCount) as $position) {
            $media = PostMedia::factory()->for($post)->create();
            $composition = AuthoringImageComposition::factory()
                ->for($post, 'post')
                ->for($plan, 'plan')
                ->create([
                    'selected_by_id' => $user->id,
                    'section_heading' => 'Why gardens matter',
                    'position' => $position,
                    // A saved passage is what makes the listing staleness-check
                    // this composition against a rebuilt passages context.
                    'passage_markdown' => 'An approved passage for photo '.$position.'.',
                    'passage_input_hash' => str_repeat('a', 64),
                ]);
            $composition->media()->attach($media->id, [
                'plan_id' => $plan->id,
                'position' => 1,
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($user)
            ->getJson(route('posts.assistant.authoring.index', $post))
            ->assertOk();

        return $queries;
    };

    $small = $buildPost(2);
    $large = $buildPost(8);

    // Measured before context builds were shared across one listing: 57
    // queries at 2 compositions and 141 at 8, i.e. ~14 per composition, every
    // two seconds while a task runs. Sharing the builds brings that to ~3.
    expect(($large - $small) / 6)->toBeLessThanOrEqual(5)
        ->and($small)->toBeLessThanOrEqual(40);
});

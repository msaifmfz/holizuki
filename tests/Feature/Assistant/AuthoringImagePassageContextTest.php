<?php

declare(strict_types=1);

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Prompts\AuthoringImagePassagesPrompt;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Str;

test('image passage context tracks the full flow and immediate included neighbors', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    PostAuthoringJourney::factory()->for($post, 'post')->create([
        'brief' => [
            'topic' => 'A neighborhood garden',
            'audience' => 'New volunteers',
            'purpose' => 'Explain the project',
        ],
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $planContext = $contexts->forPlans($post);
    $planTask = AuthoringTask::factory()->for($post, 'post')->create([
        'requested_by_id' => $user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $post->lock_version,
        'context' => $planContext,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $planContext),
    ]);
    $plan = AuthoringArtifact::factory()
        ->for($post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $post->lock_version,
            'payload' => [
                'title' => 'A practical garden guide',
                'angle' => 'Follow one volunteer day',
                'sections' => ['Why gardens matter', 'Getting started', 'Keeping momentum'],
                'estimated_length' => 'medium',
                'source_ids' => [],
            ],
        ]);
    $photos = PostMedia::factory()->count(5)->for($post)->create();
    $photos[0]->forceFill([
        'display_name' => 'Preparing the first bed',
        'alt_text' => 'Volunteers preparing soil in a raised bed.',
        'caption' => 'Preparing the shared garden bed.',
        'story_notes' => 'The author says this was the first task of the day.',
    ])->save();
    $photos[1]->forceFill([
        'display_name' => 'Sorting garden tools',
        'alt_text' => 'Garden tools arranged beside the bed.',
        'caption' => 'Tools ready for the volunteers.',
        'story_notes' => 'The author wants to explain simple preparation.',
    ])->save();
    $photos[4]->forceFill([
        'display_name' => 'Watering new plants',
        'alt_text' => 'A watering can beside newly planted seedlings.',
        'caption' => 'The first watering after planting.',
        'story_notes' => 'The author connects this moment to ongoing care.',
    ])->save();
    $definitions = [
        [AuthoringImageCompositionUsage::Include, 'Why gardens matter'],
        [AuthoringImageCompositionUsage::Exclude, null],
        [AuthoringImageCompositionUsage::Include, 'Getting started'],
        [AuthoringImageCompositionUsage::Exclude, null],
        [AuthoringImageCompositionUsage::Include, 'Keeping momentum'],
    ];
    $compositions = [];

    foreach ($definitions as $offset => [$usage, $sectionHeading]) {
        $composition = AuthoringImageComposition::factory()
            ->for($post, 'post')
            ->for($plan, 'plan')
            ->create([
                'selected_by_id' => $user->id,
                'usage' => $usage,
                'section_heading' => $sectionHeading,
                'position' => $offset + 1,
            ]);
        $composition->media()->attach($photos[$offset]->id, [
            'plan_id' => $plan->id,
            'position' => 1,
        ]);
        $compositions[] = $composition;
    }

    $compositions[0]->forceFill([
        'passage_markdown' => 'This neighboring passage must remain private.',
    ])->save();
    $guidanceContext = $contexts->forImageGuidance($post, $plan);
    $guidanceTask = AuthoringTask::factory()->for($post, 'post')->create([
        'requested_by_id' => $user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $post->lock_version,
        'context' => $guidanceContext,
        'input_hash' => $contexts->hash(
            AuthoringTaskKind::ImageGuidance,
            $guidanceContext,
        ),
    ]);
    $guidanceByPhoto = [
        0 => [
            'narrative_role' => 'Establishes the shared work.',
            'writing_angle' => 'Explain how preparing together lowers the barrier to joining.',
        ],
        1 => [
            'narrative_role' => 'Introduces the practical setup.',
            'writing_angle' => 'Show that newcomers need only a few familiar tools.',
        ],
        4 => [
            'narrative_role' => 'Connects planting to continued care.',
            'writing_angle' => 'Transition from the event to the habits that sustain it.',
        ],
    ];

    foreach ($guidanceByPhoto as $photoOffset => $guidance) {
        AuthoringArtifact::factory()
            ->for($post, 'post')
            ->for($guidanceTask, 'task')
            ->for($plan, 'parent')
            ->create([
                'type' => AuthoringArtifactType::ImageGuidance,
                'base_lock_version' => $post->lock_version,
                'payload' => [
                    'media_id' => $photos[$photoOffset]->id,
                    ...$guidance,
                ],
            ]);
    }

    $staleGuidanceTask = AuthoringTask::factory()->for($post, 'post')->create([
        'requested_by_id' => $user->id,
        'kind' => AuthoringTaskKind::ImageGuidance,
        'base_lock_version' => $post->lock_version,
        'context' => $guidanceContext,
        'input_hash' => str_repeat('0', 64),
    ]);
    AuthoringArtifact::factory()
        ->for($post, 'post')
        ->for($staleGuidanceTask, 'task')
        ->for($plan, 'parent')
        ->create([
            'type' => AuthoringArtifactType::ImageGuidance,
            'base_lock_version' => $post->lock_version,
            'payload' => [
                'media_id' => $photos[4]->id,
                'narrative_role' => 'Stale guidance that must not be exposed.',
                'writing_angle' => 'Ignore this old angle.',
            ],
        ]);

    $currentComposition = $compositions[2];
    $initialContext = $contexts->forImagePassages($post, $currentComposition);
    $initialHash = $contexts->hash(AuthoringTaskKind::ImagePassages, $initialContext);

    expect(array_column($initialContext['photo_flow_outline'], 'id'))->toBe([
        $compositions[0]->public_id,
        $compositions[1]->public_id,
        $currentComposition->public_id,
        $compositions[3]->public_id,
        $compositions[4]->public_id,
    ])->and(array_column($initialContext['photo_flow_outline'], 'included_position'))->toBe([
        1,
        null,
        2,
        null,
        3,
    ])->and($initialContext['current_photo_flow']['position'])->toBe(3)
        ->and($initialContext['current_photo_flow']['included_position'])->toBe(2)
        ->and($initialContext['current_photo_flow']['included_count'])->toBe(3)
        ->and($initialContext['current_photo_flow']['previous_included']['id'])
        ->toBe($compositions[0]->public_id)
        ->and($initialContext['current_photo_flow']['previous_included']['position'])->toBe(1)
        ->and($initialContext['current_photo_flow']['previous_included']['media'][0])->toBe([
            'id' => $photos[0]->id,
            'display_name' => 'Preparing the first bed',
            'alt_text' => 'Volunteers preparing soil in a raised bed.',
            'caption' => 'Preparing the shared garden bed.',
            'story_notes' => 'The author says this was the first task of the day.',
            'narrative_role' => 'Establishes the shared work.',
            'writing_angle' => 'Explain how preparing together lowers the barrier to joining.',
        ])
        ->and($initialContext['current_photo_flow']['next_included']['id'])
        ->toBe($compositions[4]->public_id)
        ->and($initialContext['current_photo_flow']['next_included']['position'])->toBe(5)
        ->and($initialContext['current_photo_flow']['next_included']['media'][0]['narrative_role'])
        ->toBe('Connects planting to continued care.')
        ->and(json_encode($initialContext['current_photo_flow'], JSON_THROW_ON_ERROR))
        ->not->toContain('This neighboring passage must remain private.')
        ->and($initialContext['photo_flow_outline'][0])->not->toHaveKey('media');

    $compositions[0]->forceFill([
        'usage' => AuthoringImageCompositionUsage::Exclude,
        'section_heading' => null,
    ])->save();
    $compositions[1]->forceFill([
        'usage' => AuthoringImageCompositionUsage::Include,
        'section_heading' => 'Why gardens matter',
    ])->save();
    $currentComposition->forceFill(['position' => 99])->save();
    $compositions[3]->forceFill(['position' => 3])->save();
    $currentComposition->forceFill(['position' => 4])->save();

    $updatedContext = $contexts->forImagePassages($post, $currentComposition->refresh());

    $updatedHash = $contexts->hash(AuthoringTaskKind::ImagePassages, $updatedContext);

    expect(array_column($updatedContext['photo_flow_outline'], 'id'))->toBe([
        $compositions[0]->public_id,
        $compositions[1]->public_id,
        $compositions[3]->public_id,
        $currentComposition->public_id,
        $compositions[4]->public_id,
    ])->and($updatedContext['current_photo_flow']['position'])->toBe(4)
        ->and($updatedContext['current_photo_flow']['included_position'])->toBe(2)
        ->and($updatedContext['current_photo_flow']['previous_included']['id'])
        ->toBe($compositions[1]->public_id)
        ->and($updatedContext['current_photo_flow']['previous_included']['position'])->toBe(2)
        ->and($updatedContext['current_photo_flow']['previous_included']['media'][0]['display_name'])
        ->toBe('Sorting garden tools')
        ->and($updatedContext['current_photo_flow']['previous_included']['media'][0]['writing_angle'])
        ->toBe('Show that newcomers need only a few familiar tools.')
        ->and($updatedContext['current_photo_flow']['next_included']['id'])
        ->toBe($compositions[4]->public_id)
        ->and($updatedHash)->not->toBe($initialHash);

    $photos[1]->forceFill([
        'alt_text' => 'Updated alternative text for the arranged garden tools.',
    ])->save();
    $metadataContext = $contexts->forImagePassages($post, $currentComposition->refresh());

    expect($metadataContext['current_photo_flow']['previous_included']['media'][0]['alt_text'])
        ->toBe('Updated alternative text for the arranged garden tools.')
        ->and($contexts->hash(AuthoringTaskKind::ImagePassages, $metadataContext))
        ->not->toBe($updatedHash);
});

test('image passage prompt serializes flow and neighbor context for the agent', function (): void {
    $photoFlow = [
        [
            'id' => 'before-composition',
            'usage' => 'include',
            'position' => 1,
            'included_position' => 1,
            'section_heading' => 'Opening',
            'media_ids' => [11],
        ],
        [
            'id' => 'current-composition',
            'usage' => 'include',
            'position' => 3,
            'included_position' => 2,
            'section_heading' => 'Middle',
            'media_ids' => [22, 23],
        ],
    ];
    $currentPhotoFlow = [
        'position' => 3,
        'included_position' => 2,
        'included_count' => 2,
        'previous_included' => [
            ...$photoFlow[0],
            'media' => [[
                'id' => 11,
                'display_name' => 'Preparing soil',
                'alt_text' => 'Two volunteers preparing a garden bed.',
                'caption' => 'Preparing the first shared bed.',
                'story_notes' => 'The author says this happened before planting.',
                'narrative_role' => 'Establishes the shared work.',
                'writing_angle' => 'Lead from preparation into the current planting step.',
            ]],
        ],
        'next_included' => null,
    ];
    $task = new AuthoringTask;
    $task->forceFill([
        'context' => [
            'photo_flow_outline' => $photoFlow,
            'current_photo_flow' => $currentPhotoFlow,
        ],
    ]);

    $prompt = (new AuthoringImagePassagesPrompt)->build($task);
    $serializedContext = trim((string) Str::of($prompt)->between(
        '<authoring-context-json>',
        '</authoring-context-json>',
    ));
    $promptContext = json_decode($serializedContext, true, flags: JSON_THROW_ON_ERROR);

    expect($promptContext['photo_flow_outline'])->toBe($photoFlow)
        ->and($promptContext['current_photo_flow'])->toBe($currentPhotoFlow);
});

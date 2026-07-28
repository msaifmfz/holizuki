<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\SavePostAuthoringJourney;
use App\Domain\Publishing\Enums\AuthoringJourneyKind;
use App\Domain\Publishing\Enums\AuthoringJourneyStep;
use App\Domain\Publishing\Enums\AuthoringPersonality;
use App\Domain\Publishing\Enums\AuthoringSurface;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
});

test('a creation journey is initialized once and can be resumed', function (): void {
    $expectedJourney = [
        'kind' => 'creation',
        'current_step' => 'idea',
        'completed_steps' => [],
        'brief' => [],
        'personality' => 'friendly',
        'custom_instructions' => null,
        'web_research_enabled' => false,
        'last_surface' => 'guided',
    ];

    $this->actingAs($this->user)
        ->getJson(route('posts.authoring-journey.show', $this->post))
        ->assertOk()
        ->assertJsonPath('journey', $expectedJourney);

    // Reading the guided flow reports where it would start without writing
    // anything; only an actual step persists a journey.
    expect(PostAuthoringJourney::query()->whereBelongsTo($this->post)->count())->toBe(0);

    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), [
            'current_step' => 'sources',
        ])
        ->assertOk()
        ->assertJsonPath('journey.current_step', 'sources');

    $this->actingAs($this->user)
        ->getJson(route('posts.authoring-journey.show', $this->post))
        ->assertOk()
        ->assertJsonPath('journey', [...$expectedJourney, 'current_step' => 'sources']);

    expect(PostAuthoringJourney::query()->whereBelongsTo($this->post)->count())->toBe(1);
});

test('a previously published post starts an improvement journey', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create();

    $this->actingAs($this->user)
        ->getJson(route('posts.authoring-journey.show', $post))
        ->assertOk()
        ->assertJsonPath('journey.kind', 'improvement');
});

test('a new journey starts with the authors saved assistant style', function (): void {
    $this->user->update([
        'authoring_personality' => 'custom',
        'authoring_custom_instructions' => 'Use calm, direct sentences.',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('posts.authoring-journey.show', $this->post))
        ->assertOk()
        ->assertJsonPath('journey.personality', 'custom')
        ->assertJsonPath(
            'journey.custom_instructions',
            'Use calm, direct sentences.',
        );

    // Taking the first step pins the style the post was started with.
    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), [
            'current_step' => 'sources',
        ])
        ->assertOk()
        ->assertJsonPath('journey.personality', 'custom');

    $this->user->update([
        'authoring_personality' => 'professional',
        'authoring_custom_instructions' => null,
    ]);

    // Changing the default afterwards leaves posts already under way alone.
    $this->actingAs($this->user)
        ->getJson(route('posts.authoring-journey.show', $this->post))
        ->assertOk()
        ->assertJsonPath('journey.personality', 'custom')
        ->assertJsonPath(
            'journey.custom_instructions',
            'Use calm, direct sentences.',
        );
});

test('an authoring journey accepts partial updates without replacing omitted state', function (): void {
    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), [
            'current_step' => 'plans',
            'completed_steps' => ['idea', 'sources', 'images'],
            'brief' => [
                'topic' => 'A beginner guide to night photography',
                'audience' => 'New photographers',
                'key_points' => ['Tripod technique', 'Manual exposure'],
            ],
            'personality' => 'custom',
            'custom_instructions' => 'Use an encouraging, practical voice.',
            'web_research_enabled' => true,
            'last_surface' => 'advanced',
        ])
        ->assertOk()
        ->assertJsonPath('journey.kind', 'creation')
        ->assertJsonPath('journey.current_step', 'plans')
        ->assertJsonPath('journey.completed_steps', ['idea', 'sources', 'images'])
        ->assertJsonPath('journey.brief.topic', 'A beginner guide to night photography')
        ->assertJsonPath('journey.personality', 'custom')
        ->assertJsonPath('journey.custom_instructions', 'Use an encouraging, practical voice.')
        ->assertJsonPath('journey.web_research_enabled', true)
        ->assertJsonPath('journey.last_surface', 'advanced');

    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), [
            'current_step' => 'draft',
        ])
        ->assertOk()
        ->assertJsonPath('journey.current_step', 'draft')
        ->assertJsonPath('journey.completed_steps', ['idea', 'sources', 'images'])
        ->assertJsonPath('journey.personality', 'custom')
        ->assertJsonPath('journey.web_research_enabled', true);

    // Tuning one post leaves the author's default for other posts alone.
    expect($this->user->refresh()->authoring_personality)->toBe('friendly')
        ->and($this->user->authoring_custom_instructions)->toBeNull();
});

test('an author can promote a posts writing style to their default', function (): void {
    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), [
            'personality' => 'custom',
            'custom_instructions' => 'Use an encouraging, practical voice.',
            'save_as_default' => true,
        ])
        ->assertOk()
        ->assertJsonPath('journey.personality', 'custom')
        ->assertJsonMissingPath('journey.save_as_default');

    expect($this->user->refresh()->authoring_personality)->toBe('custom')
        ->and($this->user->authoring_custom_instructions)
        ->toBe('Use an encouraging, practical voice.');

    // A later post-only change does not roll the default back.
    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), [
            'personality' => 'professional',
        ])
        ->assertOk()
        ->assertJsonPath('journey.personality', 'professional');

    expect($this->user->refresh()->authoring_personality)->toBe('custom');
});

test('authoring journey fields are validated', function (array $payload, string $field): void {
    $this->actingAs($this->user)
        ->patchJson(route('posts.authoring-journey.update', $this->post), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'kind' => [['kind' => 'unknown'], 'kind'],
    'current step' => [['current_step' => 'unknown'], 'current_step'],
    'completed steps must be valid' => [['completed_steps' => ['idea', 'unknown']], 'completed_steps.1'],
    'completed steps must be distinct' => [['completed_steps' => ['idea', 'idea']], 'completed_steps.1'],
    'brief' => [['brief' => 'not-an-object'], 'brief'],
    'personality' => [['personality' => 'unknown'], 'personality'],
    'custom instructions' => [['custom_instructions' => ['not-a-string']], 'custom_instructions'],
    'web research preference' => [['web_research_enabled' => 'sometimes'], 'web_research_enabled'],
    'surface' => [['last_surface' => 'unknown'], 'last_surface'],
]);

test('guests and reader accounts cannot resume an authoring journey', function (): void {
    $this->getJson(route('posts.authoring-journey.show', $this->post))
        ->assertUnauthorized();

    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)
        ->getJson(route('posts.authoring-journey.show', $this->post))
        ->assertForbidden();
});

test('the save action preserves one journey per post', function (): void {
    $action = resolve(SavePostAuthoringJourney::class);

    $first = $action->handle($this->post, ['current_step' => AuthoringJourneyStep::Sources]);
    $second = $action->handle($this->post, [
        'kind' => AuthoringJourneyKind::Creation,
        'current_step' => AuthoringJourneyStep::Images,
        'completed_steps' => [AuthoringJourneyStep::Idea->value, AuthoringJourneyStep::Sources->value],
        'personality' => AuthoringPersonality::Professional,
        'last_surface' => AuthoringSurface::Guided,
    ]);

    expect($second->is($first))->toBeTrue()
        ->and($second->current_step)->toBe(AuthoringJourneyStep::Images)
        ->and(PostAuthoringJourney::query()->whereBelongsTo($this->post)->count())->toBe(1);
});

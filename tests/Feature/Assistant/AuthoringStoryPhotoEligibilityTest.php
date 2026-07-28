<?php

declare(strict_types=1);

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Assistant\Services\AuthoringImageFlowReadiness;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
    PostAuthoringJourney::factory()->for($this->post, 'post')->create();
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Plans,
    ]);
    $this->plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create();
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $this->plan->id,
        'selected_by_id' => $this->user->id,
    ]);
});

test('a sole promoted story photo is retained but omitted from draft flow', function (): void {
    $photo = PostMedia::factory()->for($this->post)->create();
    $recoveredPassage = 'Keep this writing if the author restores the photo to the story.';
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($this->plan, 'plan')
        ->create([
            'selected_by_id' => $this->user->id,
            'passage_markdown' => $recoveredPassage,
            'passage_input_hash' => 'intentionally-stale-before-promotion',
        ]);
    $composition->media()->attach($photo->id, [
        'plan_id' => $this->plan->id,
        'position' => 1,
    ]);
    $photo->forceFill([
        'role' => PostMediaRole::Hero,
        'placement' => PostMediaPlacement::Featured,
    ])->save();

    $readiness = resolve(AuthoringImageFlowReadiness::class);
    $readiness->ensureReadyForDraft($this->post, $this->plan);
    $context = resolve(AuthoringContextFactory::class)
        ->forDraft($this->post, $this->plan);

    expect($context['media_ids'])->toBe([])
        ->and($context['photo_flow'])->toBe([])
        ->and($context['included_media_ids'])->toBe([])
        ->and($context['excluded_media_ids'])->toBe([])
        ->and($readiness->passageIsStale($this->post, $composition->refresh()))
        ->toBeFalse()
        ->and($composition->passage_markdown)->toBe($recoveredPassage);

    $this->assertModelExists($composition);
    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.authoring.index', $this->post))
        ->assertSuccessful()
        ->assertJsonCount(0, 'image_compositions')
        ->assertJsonCount(0, 'image_passages');
});

test('a mixed group containing a promoted photo requires flow resync', function (): void {
    $storyPhoto = PostMedia::factory()->for($this->post)->create();
    $promotedPhoto = PostMedia::factory()->for($this->post)->create([
        'role' => PostMediaRole::Hero,
        'placement' => PostMediaPlacement::Featured,
    ]);
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($this->plan, 'plan')
        ->create([
            'selected_by_id' => $this->user->id,
            'passage_markdown' => 'The two photographs originally formed one approved sequence.',
        ]);
    $composition->media()->attach([
        $storyPhoto->id => ['plan_id' => $this->plan->id, 'position' => 1],
        $promotedPhoto->id => ['plan_id' => $this->plan->id, 'position' => 2],
    ]);
    $readiness = resolve(AuthoringImageFlowReadiness::class);

    expect(
        fn () => $readiness->ensureReadyForDraft($this->post, $this->plan),
    )->toThrow(
        ValidationException::class,
        'Choose include or skip for every story photo before drafting.',
    );

    $context = resolve(AuthoringContextFactory::class)
        ->forDraft($this->post, $this->plan);

    expect($context['media_ids'])->toBe([$storyPhoto->id])
        ->and($context['photo_flow'])->toBe([])
        ->and($context['included_media_ids'])->toBe([])
        ->and($composition->refresh()->passage_markdown)
        ->toBe('The two photographs originally formed one approved sequence.');
});

test('promoted compositions cannot target or influence photo passage generation', function (): void {
    $promotedPhoto = PostMedia::factory()->for($this->post)->create([
        'role' => PostMediaRole::Hero,
        'placement' => PostMediaPlacement::Featured,
    ]);
    $storyPhoto = PostMedia::factory()->for($this->post)->create();
    $promotedComposition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($this->plan, 'plan')
        ->create([
            'selected_by_id' => $this->user->id,
            'position' => 1,
            'passage_markdown' => 'Recovery-only hero passage.',
            'passage_input_hash' => 'stale',
        ]);
    $promotedComposition->media()->attach($promotedPhoto->id, [
        'plan_id' => $this->plan->id,
        'position' => 1,
    ]);
    $storyComposition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($this->plan, 'plan')
        ->create([
            'selected_by_id' => $this->user->id,
            'position' => 2,
        ]);
    $storyComposition->media()->attach($storyPhoto->id, [
        'plan_id' => $this->plan->id,
        'position' => 1,
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $context = $contexts->forImagePassages($this->post, $storyComposition);

    expect(array_column($context['photo_flow_outline'], 'id'))
        ->toBe([$storyComposition->public_id])
        ->and($context['current_photo_flow']['included_count'])->toBe(1)
        ->and($context['current_photo_flow']['previous_included'])->toBeNull()
        ->and($context['current_photo_flow']['next_included'])->toBeNull()
        ->and(fn () => $contexts->forImagePassages(
            $this->post,
            $promotedComposition,
        ))
        ->toThrow(LogicException::class, 'not eligible for story photo writing')
        ->and(resolve(AuthoringImageFlowReadiness::class)->passageIsStale(
            $this->post,
            $promotedComposition->refresh(),
        ))
        ->toBeFalse();
});

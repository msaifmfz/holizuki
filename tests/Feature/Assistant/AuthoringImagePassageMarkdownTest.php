<?php

declare(strict_types=1);

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\AuthoringContextFactory;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Domain\Publishing\Models\PostMedia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
    PostAuthoringJourney::factory()->for($this->post, 'post')->create([
        'brief' => [
            'topic' => 'A neighborhood garden',
            'audience' => 'New volunteers',
            'purpose' => 'Explain the project',
        ],
    ]);
    $contexts = resolve(AuthoringContextFactory::class);
    $planContext = $contexts->forPlans($this->post);
    $planTask = AuthoringTask::factory()->for($this->post, 'post')->create([
        'requested_by_id' => $this->user->id,
        'kind' => AuthoringTaskKind::Plans,
        'base_lock_version' => $this->post->lock_version,
        'context' => $planContext,
        'input_hash' => $contexts->hash(AuthoringTaskKind::Plans, $planContext),
    ]);
    $this->plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($planTask, 'task')
        ->create([
            'base_lock_version' => $this->post->lock_version,
            'payload' => [
                'title' => 'A practical garden guide',
                'angle' => 'Follow one volunteer day',
                'sections' => ['Why gardens matter'],
                'estimated_length' => 'medium',
                'source_ids' => [],
            ],
        ]);
    AuthoringSelection::factory()->for($this->post)->create([
        'selected_plan_id' => $this->plan->id,
        'selected_by_id' => $this->user->id,
    ]);
    $photo = PostMedia::factory()->for($this->post)->create();
    $this->composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($this->plan, 'plan')
        ->create([
            'selected_by_id' => $this->user->id,
            'section_heading' => 'Why gardens matter',
        ]);
    $this->composition->media()->attach($photo->id, [
        'plan_id' => $this->plan->id,
        'position' => 1,
    ]);
});

test('authors can save one to three paragraphs with safe inline formatting', function (string $markdown): void {
    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $this->composition,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => $markdown,
            ],
        )
        ->assertSuccessful()
        ->assertJsonPath('image_composition.passage_markdown', $markdown);
})->with([
    'plain paragraph' => 'Volunteers prepared the garden bed together.',
    'three paragraphs' => "The first photo establishes the shared work.\n\nThe second paragraph explains the practical step.\n\nThe final paragraph returns to the wider project.",
    'inline formatting and safe links' => '<u>Neighbors</u> used **shared tools**, *steady effort*, ~~an old plan~~, `labels`, and [public guidance](https://example.com/garden).',
    'escaped image-like text' => 'The literal \![garden](https://example.com/garden.jpg) remains ordinary text.',
]);

test('manual passages reject non-paragraph or unsafe markdown', function (string $markdown): void {
    $this->actingAs($this->user)
        ->patchJson(
            route('posts.assistant.authoring.image-compositions.update', [
                $this->post,
                $this->composition,
            ]),
            [
                'selected_passage_id' => null,
                'passage_markdown' => $markdown,
            ],
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('passage_markdown');

    expect($this->composition->refresh()->passage_markdown)->toBeNull();
})->with([
    'four paragraphs' => "One.\n\nTwo.\n\nThree.\n\nFour.",
    'backtick fence' => "Before.\n\n```\ncode\n```",
    'tilde fence' => "Before.\n\n~~~\ncode\n~~~",
    'indented code' => "Before.\n\n    code",
    'ATX heading' => "## Heading\n\nParagraph.",
    'Setext heading' => "Heading\n=======\n\nParagraph.",
    'media link' => '[photo](media:12)',
    'source citation' => 'A sourced claim.[](source:12)',
    'inline image' => 'Before ![garden](https://example.com/garden.jpg) after.',
    'HTML image' => 'Before <img src="https://example.com/garden.jpg"> after.',
    'resolved reference image' => "![garden][photo]\n\n[photo]: https://example.com/garden.jpg",
    'unresolved reference image' => '![garden][missing]',
    'bullet list' => "- First\n- Second",
    'blockquote' => '> A quoted passage.',
    'unsafe link' => '[open this](javascript:alert(1))',
    'unbalanced underline' => '<u>Unclosed formatting.',
]);

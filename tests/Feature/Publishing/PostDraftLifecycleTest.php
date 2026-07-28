<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\PublishPost;
use App\Domain\Publishing\Actions\RestorePostRevision;
use App\Domain\Publishing\Actions\SavePostDraft;
use App\Domain\Publishing\Actions\SchedulePost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Events\PostContentUpdated;
use App\Domain\Publishing\Events\PostPublished;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostDraft;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->body = fn (string $text): array => [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]],
    ];
});

test('every post receives a matching private working draft', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Initial title',
        'slug' => 'initial-title',
        'body' => ($this->body)('Initial body'),
        'seo_title' => 'Initial SEO title',
    ]);

    $draft = $post->draft()->sole();

    expect($draft)
        ->toBeInstanceOf(PostDraft::class)
        ->and($draft->title)->toBe($post->title)
        ->and($draft->slug)->toBe($post->slug)
        ->and($draft->body?->toArray())->toBe($post->body?->toArray())
        ->and($draft->seo_title)->toBe($post->seo_title);
});

test('saving a working draft cannot change the public projection', function (): void {
    $liveTag = Tag::factory()->create(['name' => 'Live']);
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'title' => 'Public title',
        'slug' => 'public-title',
        'body' => ($this->body)('Public body'),
        'lock_version' => 0,
    ]);
    $post->tags()->attach($liveTag);
    Event::fake();

    $saved = resolve(SavePostDraft::class)->handle($post, [
        'title' => 'Private draft title',
        'slug' => 'private-draft-title',
        'slug_is_manual' => true,
        'body' => ($this->body)('Private draft body'),
        'tags' => ['Draft only'],
        'lock_version' => 0,
    ], $this->user);

    $public = $post->refresh();
    $draft = $saved->draft;

    expect($public->title)->toBe('Public title')
        ->and($public->slug)->toBe('public-title')
        ->and($public->body?->plainText())->toBe('Public body')
        ->and($public->tags()->pluck('name')->all())->toBe(['Live'])
        ->and($public->lock_version)->toBe(1)
        ->and($draft?->title)->toBe('Private draft title')
        ->and($draft?->slug)->toBe('private-draft-title')
        ->and($draft?->tags()->pluck('name')->all())->toBe(['Draft only'])
        ->and($post->revisions()->count())->toBe(0);

    Event::assertNotDispatched(PostContentUpdated::class);
    Event::assertNotDispatched(PostPublished::class);
});

test('publishing atomically promotes the complete draft and records its snapshot', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'title' => 'Old public title',
        'slug' => 'old-public-title',
        'body' => ($this->body)('Old public body'),
        'lock_version' => 0,
    ]);

    resolve(SavePostDraft::class)->handle($post, [
        'title' => 'New public title',
        'slug' => 'new-public-title',
        'slug_is_manual' => true,
        'excerpt' => 'New excerpt',
        'body' => ($this->body)('New public body'),
        'seo_title' => 'New SEO title',
        'tags' => ['Laravel', 'Publishing'],
        'lock_version' => 0,
    ], $this->user);

    Event::fake();
    $published = resolve(PublishPost::class)->handle($post, $this->user, expectedVersion: 1);
    $revision = $published->revisions()->latest('revision_number')->firstOrFail();

    expect($published->title)->toBe('New public title')
        ->and($published->slug)->toBe('new-public-title')
        ->and($published->excerpt)->toBe('New excerpt')
        ->and($published->body?->plainText())->toBe('New public body')
        ->and($published->seo_title)->toBe('New SEO title')
        ->and($published->tags()->pluck('name')->all())->toEqualCanonicalizing(['Laravel', 'Publishing'])
        ->and($published->search_text)->toContain('New public body')
        ->and($published->lock_version)->toBe(2)
        ->and($revision->event)->toBe(PostRevisionEvent::Published)
        ->and($revision->snapshot->title)->toBe('New public title')
        ->and($revision->snapshot->tags)->toEqualCanonicalizing(['Laravel', 'Publishing']);

    Event::assertDispatched(PostPublished::class);
});

test('restoring a revision changes only the private working draft', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'title' => 'Public title',
        'body' => ($this->body)('Public body'),
        'lock_version' => 0,
    ]);

    resolve(SavePostDraft::class)->handle($post, [
        'title' => 'Revision title',
        'slug' => 'revision-title',
        'slug_is_manual' => true,
        'body' => ($this->body)('Revision body'),
        'tags' => ['Revision tag'],
        'lock_version' => 0,
    ], $this->user, createRevision: true);
    $revision = $post->revisions()->sole();

    resolve(SavePostDraft::class)->handle($post, [
        'title' => 'Current draft title',
        'slug' => 'current-draft-title',
        'slug_is_manual' => true,
        'body' => ($this->body)('Current draft body'),
        'tags' => ['Current tag'],
        'lock_version' => 1,
    ], $this->user);

    Event::fake();
    $restored = resolve(RestorePostRevision::class)->handle(
        $post,
        $revision,
        $this->user,
        expectedVersion: 2,
    );

    expect($restored->title)->toBe('Public title')
        ->and($restored->body?->plainText())->toBe('Public body')
        ->and($restored->status)->toBe(PostStatus::Published)
        ->and($restored->draft?->title)->toBe('Revision title')
        ->and($restored->draft?->body?->plainText())->toBe('Revision body')
        ->and($restored->draft?->tags()->pluck('name')->all())->toBe(['Revision tag'])
        ->and($restored->lock_version)->toBe(3)
        ->and($restored->revisions()->latest('revision_number')->firstOrFail()->event)
        ->toBe(PostRevisionEvent::Restored);

    Event::assertNotDispatched(PostContentUpdated::class);
});

test('scheduled publishing uses the confirmed revision instead of later draft changes', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Scheduled version',
        'slug' => 'scheduled-version',
        'body' => ($this->body)('Scheduled body'),
        'lock_version' => 0,
    ]);
    $scheduledAt = now()->subMinute()->startOfSecond();

    $scheduled = resolve(SchedulePost::class)->handle(
        $post,
        $this->user,
        $scheduledAt,
        expectedVersion: 0,
    );
    $scheduledRevisionId = $scheduled->scheduled_revision_id;

    resolve(SavePostDraft::class)->handle($post, [
        'title' => 'Later private changes',
        'slug' => 'later-private-changes',
        'slug_is_manual' => true,
        'body' => ($this->body)('Later private body'),
        'lock_version' => 1,
    ], $this->user);

    $published = resolve(PublishPost::class)->publishScheduled(
        $post,
        editor: null,
        publishedAt: $scheduledAt,
    );

    expect($scheduledRevisionId)->not->toBeNull()
        ->and($published->status)->toBe(PostStatus::Published)
        ->and($published->title)->toBe('Scheduled version')
        ->and($published->body?->plainText())->toBe('Scheduled body')
        ->and($published->published_at?->equalTo($scheduledAt))->toBeTrue()
        ->and($published->scheduled_revision_id)->toBeNull()
        ->and($published->draft?->title)->toBe('Later private changes')
        ->and($published->draft?->body?->plainText())->toBe('Later private body')
        ->and($published->revisions()->findOrFail($scheduledRevisionId)->snapshot->title)
        ->toBe('Scheduled version');
});

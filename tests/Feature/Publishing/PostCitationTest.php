<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
});

test('autosave only accepts citations to sources owned by the post', function (): void {
    $ownedSource = PostSource::factory()->for($this->post)->create();
    $otherPost = Post::factory()->for($this->user, 'author')->create();
    $foreignSource = PostSource::factory()->for($otherPost)->create();

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.autosave', $this->post),
            citationAutosavePayload($this->post, $ownedSource->id),
        )
        ->assertOk();

    $this->actingAs($this->user)
        ->patchJson(
            route('posts.autosave', $this->post),
            citationAutosavePayload(
                $this->post->refresh(),
                $foreignSource->id,
            ),
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

test('publishing requires every cited source to be approved and public', function (): void {
    $source = PostSource::factory()->for($this->post)->create();
    $draft = $this->post->draft()->firstOrFail();
    $draft->body = citationBody($source->id);
    $draft->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $this->post), [
            'lock_version' => $this->post->lock_version,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    $source->forceFill(['approved_at' => now()])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $this->post), [
            'lock_version' => $this->post->lock_version,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    $source->forceFill(['visibility' => PostSourceVisibility::Public])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $this->post), [
            'lock_version' => $this->post->lock_version,
        ])
        ->assertOk();
});

test('public citation payloads are ordered deduplicated and never expose private source files', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create();
    $document = PostSource::factory()
        ->for($post)
        ->document()
        ->approved()
        ->create([
            'title' => 'Private field guide',
            'path' => 'posts/'.$post->id.'/sources/do-not-leak.pdf',
            'visibility' => PostSourceVisibility::Private,
        ]);
    $url = PostSource::factory()
        ->for($post)
        ->url()
        ->approved()
        ->create([
            'title' => 'Observatory handbook',
            'url' => 'https://docs.example.com/observatory',
            'visibility' => PostSourceVisibility::Public,
        ]);
    $note = PostSource::factory()
        ->for($post)
        ->approved()
        ->create([
            'title' => 'Author interview notes',
            'visibility' => PostSourceVisibility::Public,
        ]);
    $unapproved = PostSource::factory()
        ->for($post)
        ->url()
        ->create(['title' => 'Unreviewed finding']);

    $post->body = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Document'],
                ['type' => 'citation', 'attrs' => ['sourceId' => $document->id]],
                ['type' => 'text', 'text' => ' URL'],
                ['type' => 'citation', 'attrs' => ['sourceId' => $url->id]],
                ['type' => 'citation', 'attrs' => ['sourceId' => $document->id]],
                ['type' => 'text', 'text' => ' note'],
                ['type' => 'citation', 'attrs' => ['sourceId' => $note->id]],
                ['type' => 'citation', 'attrs' => ['sourceId' => $unapproved->id]],
            ],
        ]],
    ];
    $post->save();

    $response = $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('post.citations', 2)
            ->where('post.citations.0.number', 1)
            ->where('post.citations.0.source_id', $url->id)
            ->where('post.citations.0.title', 'Observatory handbook')
            ->where('post.citations.0.kind', 'url')
            ->where('post.citations.0.url', 'https://docs.example.com/observatory')
            ->missing('post.citations.0.path')
            ->where('post.citations.1.number', 2)
            ->where('post.citations.1.source_id', $note->id)
            ->where('post.citations.1.url', null)
            ->where('post.body.content.0.content.2.attrs.number', 1)
            ->where('post.body.content.0.content.4.attrs.number', 2));

    expect($response->getContent())
        ->not->toContain('do-not-leak.pdf')
        ->not->toContain('Private field guide')
        ->not->toContain('Unreviewed finding');
});

test('private previews may resolve unapproved citations without exposing document paths', function (): void {
    $source = PostSource::factory()
        ->for($this->post)
        ->document()
        ->create([
            'title' => 'Draft research',
            'path' => 'posts/'.$this->post->id.'/sources/private-draft.pdf',
        ]);
    $draft = $this->post->draft()->firstOrFail();
    $draft->body = citationBody($source->id);
    $draft->save();

    $response = $this->actingAs($this->user)
        ->get(route('posts.preview', $this->post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('post.citations', 1)
            ->where('post.citations.0.title', 'Draft research')
            ->where('post.citations.0.url', null)
            ->missing('post.citations.0.path'));

    expect($response->getContent())->not->toContain('private-draft.pdf');
});

/** @return array<string, mixed> */
function citationAutosavePayload(Post $post, int $sourceId): array
{
    $draft = $post->draft()->firstOrFail();

    return [
        'title' => $draft->title,
        'slug' => $draft->slug,
        'slug_is_manual' => $draft->slug_is_manual,
        'excerpt' => $draft->excerpt,
        'body' => citationBody($sourceId),
        'featured_image_alt' => $draft->featured_image_alt,
        'lock_version' => $post->lock_version,
    ];
}

/** @return array<string, mixed> */
function citationBody(int $sourceId): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'A supported claim'],
                ['type' => 'citation', 'attrs' => ['sourceId' => $sourceId]],
                ['type' => 'text', 'text' => '.'],
            ],
        ]],
    ];
}

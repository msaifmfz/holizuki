<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows the outline wizard and opens the co-writer on an empty post', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'A blank canvas',
        'body' => null,
    ]);

    visit("/posts/{$post->id}/edit")
        ->wait(1)
        ->assertSee('Start this article with AI')
        ->assertSee('Outline it')
        ->click('Co-writer')
        ->wait(1)
        ->assertSee('The co-writer reads your draft and edits it with you.')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('offers metadata and image assistance on a post with content', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'A drafted article',
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Real prose in the body.']],
            ]],
        ],
    ]);

    visit("/posts/{$post->id}/edit")
        ->wait(1)
        ->assertDontSee('Start this article with AI')
        ->assertSee('Generate all')
        ->assertSee('Review images')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

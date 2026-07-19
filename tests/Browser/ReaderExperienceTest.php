<?php

declare(strict_types=1);

use App\Domain\Publishing\Models\Post;

it('keeps article navigation usable on mobile in dark mode', function (): void {
    Post::factory()->published()->create([
        'title' => 'A calm mobile reading experience',
        'slug' => 'mobile-reader',
        'featured_image_path' => null,
        'body' => readerExperienceBody(),
    ]);

    visit('/posts/mobile-reader')
        ->on()->iPhone14Pro()
        ->inDarkMode()
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('A calm mobile reading experience')
        ->assertSee('1 min read')
        ->assertSee('On this page')
        ->click('[aria-label="Open menu"]')
        ->assertSee('Topics')
        ->assertSee('Archive')
        ->assertNoSmoke();
});

it('supports table of contents and code copying without browser errors', function (): void {
    Post::factory()->published()->create([
        'title' => 'The previous article',
        'published_at' => '2026-07-15 12:00:00',
    ]);
    Post::factory()->published()->create([
        'title' => 'A technical reader experience',
        'slug' => 'technical-reader',
        'featured_image_path' => null,
        'published_at' => '2026-07-16 12:00:00',
        'body' => readerExperienceBody(),
    ]);
    Post::factory()->published()->create([
        'title' => 'The next article',
        'published_at' => '2026-07-17 12:00:00',
    ]);

    visit('/posts/technical-reader')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('On this page')
        ->assertSee('Install dependencies')
        ->assertSee('Configure queues')
        ->assertSee('Deploy safely')
        ->assertSee('Previous post')
        ->assertSee('The previous article')
        ->assertSee('Next post')
        ->assertSee('The next article')
        ->assertSee('PHP')
        ->click('[aria-label="Copy code"]')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

/** @return array<string, mixed> */
function readerExperienceBody(): array
{
    return [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Install dependencies']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Start with a small and reliable dependency set.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Configure queues']]],
            ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => 'dispatch(new PublishPost);']]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Deploy safely']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Verify the deployment before sending readers to it.']]],
        ],
    ];
}

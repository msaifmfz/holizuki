<?php

use App\Domain\Publishing\Enums\WordCountBand;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('reader metadata is derived whenever post content changes', function (): void {
    $body = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => 'MetadataNeedle '.trim(str_repeat('word ', 225)),
            ]],
        ]],
    ];

    $post = Post::factory()->create([
        'title' => 'Reader metadata',
        'body' => $body,
    ]);

    expect($post->refresh()->reading_time_minutes)->toBe(2)
        ->and($post->word_count)->toBe(226)
        ->and($post->word_count_band)->toBe(WordCountBand::Under500)
        ->and($post->search_text)->toContain('Reader metadata')
        ->and($post->search_text)->toContain('MetadataNeedle');
});

test('word-count bands use stable exact boundaries', function (
    int $wordCount,
    WordCountBand $expected,
): void {
    expect(WordCountBand::forWordCount($wordCount))->toBe($expected);
})->with([
    'zero' => [0, WordCountBand::Under500],
    '499' => [499, WordCountBand::Under500],
    '500' => [500, WordCountBand::From500To999],
    '999' => [999, WordCountBand::From500To999],
    '1000' => [1000, WordCountBand::From1000To1499],
    '1499' => [1499, WordCountBand::From1000To1499],
    '1500' => [1500, WordCountBand::From1500To2499],
    '2499' => [2499, WordCountBand::From1500To2499],
    '2500' => [2500, WordCountBand::From2500],
]);

test('metadata rebuild supports missing-only and full modes', function (): void {
    $missing = Post::factory()->create();
    $existing = Post::factory()->create();

    DB::table('posts')->where('id', $missing->id)->update([
        'reading_time_minutes' => null,
        'word_count' => 0,
        'search_text' => null,
    ]);
    DB::table('posts')->where('id', $existing->id)->update([
        'reading_time_minutes' => 99,
        'word_count' => 999,
        'search_text' => 'keep-me',
    ]);

    $this->artisan('posts:rebuild-metadata', ['--missing' => true])->assertSuccessful();

    expect($missing->refresh()->reading_time_minutes)->not->toBeNull()
        ->and($missing->word_count)->toBeGreaterThan(0)
        ->and($missing->search_text)->not->toBeNull()
        ->and($existing->refresh()->reading_time_minutes)->toBe(99)
        ->and($existing->word_count)->toBe(999)
        ->and($existing->search_text)->toBe('keep-me');

    $this->artisan('posts:rebuild-metadata')->assertSuccessful();

    expect($existing->refresh()->reading_time_minutes)->not->toBe(99)
        ->and($existing->search_text)->not->toBe('keep-me');
});

test('public post payloads include reading time caption toc and enriched headings', function (): void {
    $post = Post::factory()->published()->create([
        'featured_image_caption' => 'Moonlight over the observatory',
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'First section']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => trim(str_repeat('word ', 226))]]],
                ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Repeated section']]],
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Repeated section']]],
            ],
        ],
    ]);

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('post.reading_time_minutes', 2)
            ->where('post.featured_image_caption', 'Moonlight over the observatory')
            ->has('table_of_contents', 3)
            ->where('table_of_contents.0.id', 'first-section')
            ->where('table_of_contents.1.id', 'repeated-section')
            ->where('table_of_contents.2.id', 'repeated-section-2')
            ->where('post.body.content.0.attrs.id', 'first-section')
            ->where('post.body.content.2.attrs.id', 'repeated-section')
            ->where('post.body.content.3.attrs.id', 'repeated-section-2')
            ->where('seo.json_ld.@graph.0.timeRequired', 'PT2M'));
});

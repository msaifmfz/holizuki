<?php

use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-ws-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);

    $this->user = User::factory()->create();
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

test('materialize writes the draft, metadata, and image manifest', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Workspace post',
        'excerpt' => 'An excerpt.',
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Hello']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'World.']]],
            ],
        ],
    ]);
    $post->tags()->attach(Tag::factory()->create(['name' => 'laravel', 'slug' => 'laravel']));

    Storage::disk('public')->put('posts/'.$post->id.'/inline/pic.webp', 'binary');
    $media = PostMedia::factory()->for($post, 'post')->create(['path' => 'posts/'.$post->id.'/inline/pic.webp']);

    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post);
    $path = $workspace->pathFor($post);

    expect(File::get($path.'/draft.md'))->toBe("## Hello\n\nWorld.\n");

    $meta = json_decode(File::get($path.'/meta.json'), true);
    expect($meta['title'])->toBe('Workspace post')
        ->and($meta['excerpt'])->toBe('An excerpt.')
        ->and($meta['tags'])->toBe(['laravel']);

    $manifest = json_decode(File::get($path.'/images/manifest.json'), true);
    $inlineEntries = array_values(array_filter($manifest, fn (array $entry): bool => $entry['role'] === 'inline'));

    expect($inlineEntries)->toHaveCount(1)
        ->and($inlineEntries[0]['mediaId'])->toBe($media->id)
        ->and($inlineEntries[0]['usedInDraft'])->toBeFalse()
        ->and(File::exists($path.'/'.$inlineEntries[0]['file']))->toBeTrue();
});

test('the media map prefers the src stored in the body', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $media = PostMedia::factory()->for($post, 'post')->create(['width' => 800, 'height' => 600]);

    $post->forceFill([
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'image',
                'attrs' => ['mediaId' => $media->id, 'src' => '/custom/src.webp', 'alt' => 'Pic', 'caption' => null, 'width' => 800, 'height' => 600],
            ]],
        ],
    ])->save();

    $map = resolve(WorkspaceManager::class)->mediaMap($post->refresh());

    expect($map[$media->id]['src'])->toBe('/custom/src.webp')
        ->and($map[$media->id]['width'])->toBe(800);
});

test('reading meta tolerates junk the agent may write', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post);

    File::put($workspace->pathFor($post).'/meta.json', json_encode([
        'title' => 'Kept',
        'tags' => ['ok', 123, ['nested']],
        'excerpt' => 42,
        'unknown_key' => 'dropped',
    ]));

    $meta = $workspace->readMeta($post);

    expect($meta['title'])->toBe('Kept')
        ->and($meta['tags'])->toBe(['ok'])
        ->and($meta)->not->toHaveKey('excerpt')
        ->and($meta)->not->toHaveKey('unknown_key');
});

test('destroy removes the workspace directory', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post);

    expect(File::isDirectory($workspace->pathFor($post)))->toBeTrue();

    $workspace->destroy($post);

    expect(File::isDirectory($workspace->pathFor($post)))->toBeFalse();
});

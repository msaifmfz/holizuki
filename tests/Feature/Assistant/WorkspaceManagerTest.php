<?php

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostMediaRole;
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

function workspaceTurn(Post $post): AssistantTurn
{
    return AssistantTurn::factory()
        ->for(AssistantSession::factory()->for($post, 'post'), 'session')
        ->running()
        ->create();
}

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
    $post->draft()->firstOrFail()->tags()->attach(
        Tag::factory()->create(['name' => 'laravel', 'slug' => 'laravel']),
    );

    Storage::disk('public')->put('posts/'.$post->id.'/inline/pic.webp', 'binary');
    $media = PostMedia::factory()->for($post, 'post')->create(['path' => 'posts/'.$post->id.'/inline/pic.webp']);

    $workspace = resolve(WorkspaceManager::class);
    $turn = workspaceTurn($post);
    $workspace->materialize($post, $turn);
    $path = $workspace->pathFor($post, $turn);

    expect(File::get($path.'/draft.md'))->toBe("## Hello\n\nWorld.\n");

    $meta = json_decode(File::get($path.'/meta.json'), true);
    expect($meta['title'])->toBe('Workspace post')
        ->and($meta['excerpt'])->toBe('An excerpt.')
        ->and($meta['tags'])->toBe(['laravel']);

    $manifest = json_decode(File::get($path.'/images/manifest.json'), true);
    $mediaEntries = array_values(array_filter(
        $manifest,
        fn (array $entry): bool => $entry['mediaId'] !== null,
    ));

    expect($mediaEntries)->toHaveCount(1)
        ->and($mediaEntries[0]['mediaId'])->toBe($media->id)
        ->and($mediaEntries[0]['role'])->toBe('unassigned')
        ->and($mediaEntries[0]['storyPriority'])->toBe('required')
        ->and($mediaEntries[0]['usedInDraft'])->toBeFalse()
        ->and(File::exists($path.'/'.$mediaEntries[0]['file']))->toBeTrue();
});

test('authoring workspaces contain only the story media IDs in the task context', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $storyPath = 'posts/'.$post->id.'/inline/story.webp';
    $heroPath = 'posts/'.$post->id.'/inline/old-hero.webp';
    $featuredPath = 'posts/'.$post->id.'/featured/current.webp';
    Storage::disk('public')->put($storyPath, 'story');
    Storage::disk('public')->put($heroPath, 'hero');
    Storage::disk('public')->put($featuredPath, 'featured');
    $story = PostMedia::factory()->for($post, 'post')->create(['path' => $storyPath]);
    PostMedia::factory()->for($post, 'post')->create([
        'path' => $heroPath,
        'role' => PostMediaRole::Hero,
    ]);
    $post->draft()->update(['featured_image_path' => $featuredPath]);
    $turn = workspaceTurn($post);
    $turn->forceFill([
        'context' => ['authoring_media_ids' => [$story->id]],
    ])->save();
    $workspace = resolve(WorkspaceManager::class);

    $workspace->materialize($post->load('media'), $turn);
    $manifest = json_decode(
        File::get($workspace->pathFor($post, $turn).'/images/manifest.json'),
        true,
    );

    expect($manifest)->toHaveCount(1)
        ->and($manifest[0]['mediaId'])->toBe($story->id);
});

test('the media map prefers the src stored in the body', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $media = PostMedia::factory()->for($post, 'post')->create(['width' => 800, 'height' => 600]);

    $post->draft()->update([
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'image',
                'attrs' => ['mediaId' => $media->id, 'src' => '/custom/src.webp', 'alt' => 'Pic', 'caption' => null, 'width' => 800, 'height' => 600],
            ]],
        ],
    ]);

    $map = resolve(WorkspaceManager::class)->mediaMap($post->refresh());

    expect($map[$media->id]['src'])->toBe('/custom/src.webp')
        ->and($map[$media->id]['width'])->toBe(800);
});

test('reading meta rejects malformed agent values instead of silently filtering them', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $turn = workspaceTurn($post);
    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post, $turn);

    File::put($workspace->pathFor($post, $turn).'/meta.json', json_encode([
        'title' => 'Kept',
        'tags' => ['ok', 123, ['nested']],
        'excerpt' => 42,
        'unknown_key' => 'dropped',
    ]));

    expect(fn () => $workspace->readMeta($post, $turn))
        ->toThrow(UnexpectedValueException::class);
});

test('destroy removes the workspace directory', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $turn = workspaceTurn($post);
    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post, $turn);

    expect(File::isDirectory($workspace->rootFor($post)))->toBeTrue();

    $workspace->destroy($post);

    expect(File::isDirectory($workspace->rootFor($post)))->toBeFalse();
});

test('workspace images are independent read-only copies', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    Storage::disk('public')->put('posts/'.$post->id.'/inline/pic.webp', 'original');
    PostMedia::factory()->for($post, 'post')->create([
        'path' => 'posts/'.$post->id.'/inline/pic.webp',
    ]);
    $turn = workspaceTurn($post);
    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post, $turn);
    $copy = collect(File::files($workspace->pathFor($post, $turn).'/images'))
        ->first(fn (SplFileInfo $file): bool => $file->getFilename() !== 'manifest.json');

    expect($copy)->not->toBeNull()
        ->and(fileperms((string) $copy?->getRealPath()) & 0777)->toBe(0444);

    chmod((string) $copy?->getRealPath(), 0600);
    File::put((string) $copy?->getRealPath(), 'changed');

    expect(Storage::disk('public')->get('posts/'.$post->id.'/inline/pic.webp'))->toBe('original');
});

test('workspace readers reject symbolic-link output', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $turn = workspaceTurn($post);
    $workspace = resolve(WorkspaceManager::class);
    $workspace->materialize($post, $turn);
    $metaPath = $workspace->pathFor($post, $turn).'/meta.json';
    $outside = $this->workspaceRoot.'/outside.json';
    File::put($outside, '{}');
    File::delete($metaPath);
    symlink($outside, $metaPath);

    expect(fn () => $workspace->readMeta($post, $turn))
        ->toThrow(UnexpectedValueException::class);
});

test('image limits are checked before any image is copied', function (): void {
    config()->set('assistant.limits.image_count', 1);
    $post = Post::factory()->for($this->user, 'author')->create();

    foreach (['one.webp', 'two.webp'] as $filename) {
        $path = 'posts/'.$post->id.'/inline/'.$filename;
        Storage::disk('public')->put($path, 'image');
        PostMedia::factory()->for($post, 'post')->create(['path' => $path]);
    }

    $turn = workspaceTurn($post);
    $workspace = resolve(WorkspaceManager::class);

    expect(fn () => $workspace->materialize($post->load('media'), $turn))
        ->toThrow(UnexpectedValueException::class, 'too many images');

    expect(File::files($workspace->pathFor($post, $turn).'/images'))->toBe([]);
});

test('tasks that cannot read images are not blocked by image limits', function (): void {
    config()->set('assistant.limits.image_count', 0);
    $post = Post::factory()->for($this->user, 'author')->create();
    $path = 'posts/'.$post->id.'/inline/pic.webp';
    Storage::disk('public')->put($path, 'image');
    PostMedia::factory()->for($post, 'post')->create(['path' => $path]);
    $turn = workspaceTurn($post);
    $turn->forceFill(['task_type' => AssistantTaskType::Metadata])->save();
    $workspace = resolve(WorkspaceManager::class);

    $workspace->materialize($post->load('media'), $turn);

    expect(json_decode(
        File::get($workspace->pathFor($post, $turn).'/images/manifest.json'),
        true,
    ))->toBe([]);
});

test('materializing a run removes unreadable sibling run directories', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $turn = workspaceTurn($post);
    $workspace = resolve(WorkspaceManager::class);
    $staleRun = $workspace->rootFor($post).'/runs/stale-run';
    File::ensureDirectoryExists($staleRun);
    File::put($staleRun.'/secret.txt', 'old output');

    $workspace->materialize($post, $turn);

    expect(File::isDirectory($staleRun))->toBeFalse()
        ->and(File::isDirectory($workspace->pathFor($post, $turn)))->toBeTrue();
});

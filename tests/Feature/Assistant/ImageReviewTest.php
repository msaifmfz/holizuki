<?php

use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAgentRunner;

beforeEach(function (): void {
    Storage::fake('public');
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-ws-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);

    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'featured_image_path' => null,
        'featured_image_alt' => null,
        'body' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Some prose.']]]],
        ],
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

function fakeImageRunner(array $script): FakeAgentRunner
{
    $runner = new FakeAgentRunner($script);
    app()->instance(AgentRunner::class, $runner);

    return $runner;
}

test('a post without images cannot be reviewed', function (): void {
    fakeImageRunner([]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.images', $this->post))
        ->assertUnprocessable();
});

test('image files and the manifest are materialized for the agent to see', function (): void {
    Storage::disk('public')->put('posts/'.$this->post->id.'/inline/pic.webp', 'inline-binary');
    Storage::disk('public')->put('posts/'.$this->post->id.'/featured.webp', 'featured-binary');
    $media = PostMedia::factory()->for($this->post, 'post')->create(['path' => 'posts/'.$this->post->id.'/inline/pic.webp']);
    $this->post->forceFill(['featured_image_path' => 'posts/'.$this->post->id.'/featured.webp'])->save();

    $manifestSeen = null;
    fakeImageRunner([
        function (AgentRequest $request) use (&$manifestSeen): void {
            $manifestSeen = json_decode((string) file_get_contents($request->workspacePath.'/images/manifest.json'), true);
        },
        AgentEvent::result(isError: false, text: 'Reviewed.', costUsd: null, durationMs: 700),
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.images', $this->post))
        ->assertSuccessful()
        ->streamedContent();

    expect($manifestSeen)->toHaveCount(2)
        ->and($manifestSeen[0]['role'])->toBe('inline')
        ->and($manifestSeen[0]['mediaId'])->toBe($media->id)
        ->and($manifestSeen[1]['role'])->toBe('featured')
        ->and($manifestSeen[1]['file'])->toBe('images/featured.webp');
});

test('alt text and placement edits become reviewable changes', function (): void {
    Storage::disk('public')->put('posts/'.$this->post->id.'/inline/pic.webp', 'binary');
    Storage::disk('public')->put('posts/'.$this->post->id.'/featured.webp', 'binary');
    $media = PostMedia::factory()->for($this->post, 'post')->create(['path' => 'posts/'.$this->post->id.'/inline/pic.webp']);
    $this->post->forceFill(['featured_image_path' => 'posts/'.$this->post->id.'/featured.webp'])->save();

    $runner = fakeImageRunner([
        function (AgentRequest $request) use ($media): void {
            $draft = (string) file_get_contents($request->workspacePath.'/draft.md');
            file_put_contents(
                $request->workspacePath.'/draft.md',
                $draft."\n![A barista pouring latte art into a white cup](media:{$media->id})\n",
            );

            $metaPath = $request->workspacePath.'/meta.json';
            $meta = json_decode((string) file_get_contents($metaPath), true);
            $meta['featured_image_alt'] = 'An espresso machine on a wooden counter';
            file_put_contents($metaPath, json_encode($meta));
        },
        AgentEvent::result(isError: false, text: 'Added the unused photo and fixed alt text.', costUsd: null, durationMs: 1500),
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.images', $this->post))
        ->assertSuccessful()
        ->streamedContent();

    expect($runner->lastRequest?->model)->toBe(config('assistant.models.images'));

    $turn = AssistantTurn::query()->firstOrFail();
    expect($turn->task_type)->toBe(AssistantTaskType::Images);

    $changes = $turn->changes()->get();
    expect($changes->pluck('type')->all())
        ->toEqualCanonicalizing([AssistantChangeType::Body, AssistantChangeType::FeaturedImageAlt]);

    $bodyChange = $changes->firstWhere('type', AssistantChangeType::Body);
    expect($bodyChange?->payload['new_blocks'])->toContain('A barista pouring latte art');
});

test('accepting a placement change persists the image into the body', function (): void {
    Storage::disk('public')->put('posts/'.$this->post->id.'/inline/pic.webp', 'binary');
    $media = PostMedia::factory()->for($this->post, 'post')->create(['path' => 'posts/'.$this->post->id.'/inline/pic.webp']);

    fakeImageRunner([
        function (AgentRequest $request) use ($media): void {
            $draft = (string) file_get_contents($request->workspacePath.'/draft.md');
            file_put_contents(
                $request->workspacePath.'/draft.md',
                $draft."\n![A close-up of fresh coffee beans](media:{$media->id})\n",
            );
        },
        AgentEvent::result(isError: false, text: 'Placed the photo.', costUsd: null, durationMs: 600),
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.images', $this->post))
        ->assertSuccessful()
        ->streamedContent();

    $change = AssistantTurn::query()->firstOrFail()->changes()->firstOrFail();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful();

    $body = $this->post->refresh()->body;
    expect($body?->referencedMediaIds())->toBe([$media->id]);
});

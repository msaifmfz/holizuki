<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\ForceDeletePost;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
});

test('guests cannot access post sources', function (): void {
    $this->get(route('posts.sources.index', $this->post))
        ->assertRedirect(route('login'));

    $this->post(route('posts.sources.notes.store', $this->post), [
        'content' => 'A private research note.',
    ])->assertRedirect(route('login'));
});

test('administrators can add notes and public source URLs without fetching them', function (): void {
    Http::fake();

    $this->actingAs($this->user)
        ->postJson(route('posts.sources.notes.store', $this->post), [
            'content' => 'Interview notes about practical astronomy for families.',
        ])
        ->assertCreated()
        ->assertJsonPath('source.kind', 'note')
        ->assertJsonPath('source.visibility', 'private')
        ->assertJsonPath('source.approved', false);

    $this->actingAs($this->user)
        ->postJson(route('posts.sources.urls.store', $this->post), [
            'title' => 'Observatory handbook',
            'url' => 'https://docs.example.com/observatory-handbook',
        ])
        ->assertCreated()
        ->assertJsonPath('source.kind', 'url')
        ->assertJsonPath('source.url', 'https://docs.example.com/observatory-handbook');

    Http::assertNothingSent();

    expect(PostSource::query()->whereBelongsTo($this->post)->count())->toBe(2)
        ->and(PostSource::query()->where('kind', PostSourceKind::Url)->firstOrFail()->approved_at)->toBeNull();
});

test('source URLs reject obvious local and reserved destinations', function (string $url): void {
    $this->actingAs($this->user)
        ->postJson(route('posts.sources.urls.store', $this->post), ['url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    $this->assertDatabaseCount('post_sources', 0);
})->with([
    'localhost' => 'http://localhost/admin',
    'fully qualified localhost' => 'http://localhost./admin',
    'loopback ipv4' => 'http://127.0.0.1/secrets',
    'loopback ipv6' => 'http://[::1]/secrets',
    'link-local metadata' => 'http://169.254.169.254/latest/meta-data',
    'integer loopback' => 'http://2130706433/secrets',
    'hex loopback' => 'http://0x7f000001/secrets',
    'credential-bearing url' => 'https://user:password@example.com/private',
]);

test('documents are batch uploaded privately and only become public by explicit opt in', function (): void {
    $pdf = UploadedFile::fake()->createWithContent('field-guide.pdf', "%PDF-1.4\n%%EOF");
    $markdown = UploadedFile::fake()->createWithContent('notes.md', "# Night sky\n\nBring a red torch.");

    $response = $this->actingAs($this->user)
        ->postJson(route('posts.sources.documents.store', $this->post), [
            'documents' => [$pdf, $markdown],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'sources')
        ->assertJsonMissingPath('sources.0.path')
        ->assertJsonPath('sources.0.visibility', 'private');

    $source = PostSource::query()
        ->whereBelongsTo($this->post)
        ->where('original_name', 'field-guide.pdf')
        ->firstOrFail();

    expect($source->kind)->toBe(PostSourceKind::Document)
        ->and($source->visibility)->toBe(PostSourceVisibility::Private)
        ->and($source->isApproved())->toBeFalse()
        ->and($source->path)->not->toContain('field-guide');
    Storage::disk('local')->assertExists((string) $source->path);
    Storage::disk('public')->assertMissing((string) $source->path);

    $this->actingAs($this->user)
        ->get(route('posts.sources.download', [$this->post, $source]))
        ->assertOk()
        ->assertDownload('field-guide.pdf');

    $this->actingAs($this->user)
        ->patchJson(route('posts.sources.update', [$this->post, $source]), [
            'title' => 'Approved field guide',
            'approved' => true,
            'visibility' => 'public',
        ])
        ->assertOk()
        ->assertJsonPath('source.title', 'Approved field guide')
        ->assertJsonPath('source.approved', true)
        ->assertJsonPath('source.visibility', 'public');

    expect($source->refresh()->approved_at)->not->toBeNull()
        ->and($source->visibility)->toBe(PostSourceVisibility::Public);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.sources.destroy', [$this->post, $source]))
        ->assertNoContent();

    Storage::disk('local')->assertMissing((string) $source->path);
    $this->assertModelMissing($source);
    expect($response->json('sources.0'))->not->toHaveKey('path');
});

test('document uploads reject disguised or unsupported files', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('posts.sources.documents.store', $this->post), [
            'documents' => [
                UploadedFile::fake()->createWithContent('malware.pdf', 'not a pdf'),
                UploadedFile::fake()->createWithContent('script.php', '<?php echo "unsafe";'),
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['documents.0', 'documents.1']);

    $this->assertDatabaseCount('post_sources', 0);
    Storage::disk('local')->assertDirectoryEmpty('posts/'.$this->post->id.'/sources');
});

test('source records cannot be managed through a different post', function (): void {
    $otherPost = Post::factory()->for($this->user, 'author')->create();
    $source = PostSource::factory()->for($otherPost)->document()->create();

    $this->actingAs($this->user)
        ->patchJson(route('posts.sources.update', [$this->post, $source]), ['approved' => true])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->get(route('posts.sources.download', [$this->post, $source]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->deleteJson(route('posts.sources.destroy', [$this->post, $source]))
        ->assertNotFound();

    $this->assertModelExists($source);
});

test('permanent post deletion removes private source documents', function (): void {
    $source = PostSource::factory()->for($this->post)->document()->create([
        'path' => 'posts/'.$this->post->id.'/sources/private.pdf',
    ]);
    Storage::disk('local')->put((string) $source->path, '%PDF-1.4');

    resolve(ForceDeletePost::class)->handle($this->post);

    Storage::disk('local')->assertMissing((string) $source->path);
    $this->assertModelMissing($source);
});

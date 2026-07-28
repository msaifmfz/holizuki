<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Actions\BuildReaderDocument;
use App\Domain\Taxonomy\Models\Category;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('seeding twice rewrites the fixtures in place instead of duplicating them', function (): void {
    $this->artisan('db:seed')->assertExitCode(0);

    $posts = Post::query()->count();
    $categories = Category::query()->count();
    $users = User::query()->count();

    expect($posts)->toBeGreaterThan(0);

    $this->artisan('db:seed')->assertExitCode(0);

    expect(Post::query()->count())->toBe($posts)
        ->and(Category::query()->count())->toBe($categories)
        ->and(User::query()->count())->toBe($users);
});

test('seeding covers every post status the admin filters on', function (): void {
    $this->artisan('db:seed')->assertExitCode(0);

    expect(Post::query()->where('status', PostStatus::Published)->count())->toBeGreaterThan(0)
        ->and(Post::query()->scheduled()->count())->toBeGreaterThan(0)
        ->and(Post::query()->where('status', PostStatus::Draft)->whereNull('scheduled_at')->count())->toBeGreaterThan(0);
});

test('published posts carry enough headings for a table of contents', function (): void {
    $this->artisan('db:seed')->assertExitCode(0);

    $buildReaderDocument = resolve(BuildReaderDocument::class);

    Post::query()->where('status', PostStatus::Published)->get()
        ->each(function (Post $post) use ($buildReaderDocument): void {
            // PostTableOfContents hides itself below three entries, so anything
            // shorter would leave the sidebar untested during local review.
            expect($buildReaderDocument->forReaders($post)['table_of_contents'])
                ->toHaveCount(3, "{$post->slug} needs three headings");
        });
});

test('posts are left without a featured image when the media disk is empty', function (): void {
    $this->artisan('db:seed')->assertExitCode(0);

    expect(Post::query()->whereNotNull('featured_image_path')->count())->toBe(0);
});

test('posts cycle through whatever featured images are on disk', function (): void {
    Storage::disk('public')->put('posts/one.webp', 'x');
    Storage::disk('public')->put('posts/two.jpg', 'x');
    Storage::disk('public')->put('posts/notes.txt', 'x');

    $this->artisan('db:seed')->assertExitCode(0);

    $paths = Post::query()->pluck('featured_image_path');

    expect($paths->filter()->count())->toBe($paths->count())
        ->and($paths->unique()->sort()->values()->all())
        ->toBe(['posts/one.webp', 'posts/two.jpg']);
});

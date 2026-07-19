<?php

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Mail\CommentModeratedMail;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Mail;

test('only verified readers can submit escaped plain text comments', function (): void {
    $post = Post::factory()->published()->create();
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->post(route('public.comments.store', $post), [
        'body' => "Hello <script>alert('x')</script>\nSecond line",
    ])->assertRedirect();

    $comment = Comment::query()->sole();

    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and($comment->body)->toContain('&lt;script&gt;')
        ->and($comment->displayBody())->toContain("<script>alert('x')</script>\nSecond line")
        ->and($comment->submitted_at->diffInMinutes($comment->edit_deadline_at))->toBe(15.0);
});

test('author replies publish immediately and edits keep them approved', function (): void {
    $post = Post::factory()->published()->create();
    $administrator = User::factory()->create();

    $this->actingAs($administrator)->post(route('public.comments.store', $post), [
        'body' => 'Author reply',
    ])->assertRedirect();

    $comment = Comment::query()->sole();
    expect($comment->status)->toBe(CommentStatus::Approved)
        ->and($comment->approved_at)->not->toBeNull();

    $this->get(route('public.posts.show', $post->slug))
        ->assertInertia(fn ($page) => $page
            ->where('comments.data.0.is_author', true));

    $this->actingAs($administrator)->patch(route('public.comments.update', $comment), [
        'body' => 'Edited author reply',
    ])->assertRedirect();

    expect($comment->refresh()->status)->toBe(CommentStatus::Approved)
        ->and($comment->approved_at)->not->toBeNull();
});

test('unverified readers are redirected to verification before commenting', function (): void {
    $post = Post::factory()->published()->create();
    $reader = User::factory()->reader()->unverified()->create();

    $this->actingAs($reader)->post(route('public.comments.store', $post), [
        'body' => 'A comment',
    ])->assertRedirect(route('verification.notice'));
});

test('identical comments on one post are blocked for twenty four hours', function (): void {
    $post = Post::factory()->published()->create();
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->post(route('public.comments.store', $post), ['body' => 'Same body'])
        ->assertRedirect();
    $this->actingAs($reader)->post(route('public.comments.store', $post), ['body' => 'Same body'])
        ->assertSessionHasErrors('body');

    expect(Comment::query()->count())->toBe(1);
});

test('comment submission is limited to five per reader per hour', function (): void {
    $post = Post::factory()->published()->create();
    $reader = User::factory()->reader()->create();

    foreach (range(1, 5) as $number) {
        $this->actingAs($reader)->post(route('public.comments.store', $post), [
            'body' => 'Comment '.$number,
        ])->assertRedirect();
    }

    $this->actingAs($reader)->post(route('public.comments.store', $post), [
        'body' => 'Comment 6',
    ])->assertTooManyRequests();
});

test('owners may edit and delete for fifteen minutes and approved edits return to pending', function (): void {
    $reader = User::factory()->reader()->create();
    $comment = Comment::factory()->approved()->create([
        'user_id' => $reader->id,
        'edit_deadline_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($reader)->patch(route('public.comments.update', $comment), [
        'body' => 'Updated body',
    ])->assertRedirect();

    expect($comment->refresh()->status)->toBe(CommentStatus::Pending)
        ->and($comment->approved_at)->toBeNull();

    $this->actingAs($reader)->delete(route('public.comments.destroy', $comment))->assertRedirect();
    expect($comment->refresh()->status)->toBe(CommentStatus::Deleted);
});

test('ownership changes are forbidden after fifteen minutes', function (): void {
    $reader = User::factory()->reader()->create();
    $comment = Comment::factory()->create([
        'user_id' => $reader->id,
        'edit_deadline_at' => now()->subSecond(),
    ]);

    $this->actingAs($reader)->patch(route('public.comments.update', $comment), [
        'body' => 'Too late',
    ])->assertForbidden();
    $this->actingAs($reader)->delete(route('public.comments.destroy', $comment))->assertForbidden();
});

test('administrators cannot use the reader edit endpoint to rewrite comment content', function (): void {
    $administrator = User::factory()->create();
    $comment = Comment::factory()->create([
        'edit_deadline_at' => now()->addMinutes(15),
    ]);
    $originalBody = $comment->body;

    $this->actingAs($administrator)->patch(route('public.comments.update', $comment), [
        'body' => 'A moderator rewrite',
    ])->assertForbidden();

    expect($comment->refresh()->body)->toBe($originalBody);
});

test('administrators approve or reject without rewriting reader content and queue mail', function (): void {
    Mail::fake();
    $administrator = User::factory()->create();
    $comment = Comment::factory()->create();
    $originalBody = $comment->body;

    $this->actingAs($administrator)->patch(route('community.comments.update', $comment), [
        'status' => CommentStatus::Rejected->value,
        'reason' => 'Please keep this on topic.',
    ])->assertRedirect();

    expect($comment->refresh()->status)->toBe(CommentStatus::Rejected)
        ->and($comment->body)->toBe($originalBody)
        ->and($comment->moderation_reason)->toBe('Please keep this on topic.');
    Mail::assertQueued(CommentModeratedMail::class);
});

test('public article props include approved comments only', function (): void {
    $post = Post::factory()->published()->create();
    Comment::factory()->approved()->create(['post_id' => $post->id, 'body' => 'Approved']);
    Comment::factory()->create(['post_id' => $post->id, 'body' => 'Pending']);
    Comment::factory()->rejected()->create(['post_id' => $post->id, 'body' => 'Rejected']);

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.body', 'Approved'));
});

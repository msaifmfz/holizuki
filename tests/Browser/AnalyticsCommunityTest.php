<?php

declare(strict_types=1);

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;

beforeEach(function (): void {
    config()->set([
        'analytics.collection_enabled' => true,
        'analytics.allow_non_production_collection' => true,
        'analytics.measurement_id' => null,
    ]);
});

it('supports anonymous consent choices and withdrawal from the footer manager', function (): void {
    $page = visit('/')
        ->wait(1)
        ->assertSee('Privacy choices')
        ->assertScript("document.querySelector('#holizuki-ga4') === null")
        ->click('@analytics-decline')
        ->assertScript(
            "JSON.parse(localStorage.getItem('holizuki.analytics-consent')).status",
            'declined',
        )
        ->click('Privacy choices')
        ->click('@analytics-accept')
        ->assertScript(
            "JSON.parse(localStorage.getItem('holizuki.analytics-consent')).status",
            'accepted',
        );

    $page->click('Privacy choices')
        ->click('@analytics-decline')
        ->wait(1)
        ->assertScript(
            "JSON.parse(localStorage.getItem('holizuki.analytics-consent')).status",
            'declined',
        )
        ->assertNoSmoke();
});

it('lets a verified reader submit a pending plain-text comment', function (): void {
    $reader = User::factory()->reader()->create(['name' => 'Browser Reader']);
    $post = Post::factory()->published()->create([
        'title' => 'A browser-tested article',
        'slug' => 'browser-comments',
        'featured_image_path' => null,
    ]);
    $this->actingAs($reader);

    visit('/posts/browser-comments')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('A browser-tested article')
        ->type('body', "Useful article.\nThank you!")
        ->click('@submit-comment')
        ->wait(1)
        ->assertSee('Your comment is pending moderation.')
        ->assertSee('Your private comments')
        ->assertNoSmoke();

    $comment = Comment::query()->sole();

    expect($comment->user_id)->toBe($reader->id)
        ->and($comment->post_id)->toBe($post->id)
        ->and($comment->status)->toBe(CommentStatus::Pending);
});

it('keeps confirmation GET inert and confirms through the browser POST action', function (): void {
    $token = str_repeat('d', 64);
    $subscriber = NewsletterSubscriber::factory()->create([
        'confirmation_token_hash' => hash('sha256', $token),
        'confirmation_expires_at' => now()->addHours(48),
    ]);

    visit(route('newsletter.confirm.show', $token))
        ->wait(1)
        ->assertSee('Confirm your subscription')
        ->assertNoSmoke();

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Pending);

    visit(route('newsletter.confirm.show', $token))
        ->click('@confirm-subscription')
        ->wait(1)
        ->assertSee('You’re subscribed')
        ->assertNoSmoke();

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Confirmed);
});

it('never loads reader analytics for an administrator on a public page', function (): void {
    config()->set('analytics.measurement_id', 'G-BROWSERTEST');
    $administrator = User::factory()->create();
    Post::factory()->published()->create([
        'slug' => 'administrator-exclusion',
        'featured_image_path' => null,
    ]);
    $this->actingAs($administrator);

    visit('/posts/administrator-exclusion')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->click('@analytics-accept')
        ->wait(1)
        ->assertScript("document.querySelector('#holizuki-ga4') === null")
        ->assertNoSmoke();
});

it('renders every analytics and community workspace across responsive themes', function (): void {
    config()->set('analytics.dashboard_enabled', true);
    $administrator = User::factory()->create();
    $post = Post::factory()->published()->create();
    $this->actingAs($administrator);

    visit([
        '/dashboard',
        '/dashboard/posts',
        "/dashboard/posts/{$post->id}",
        '/dashboard/audience',
        '/dashboard/goals',
        '/dashboard/achievements',
        '/dashboard/analytics/settings',
        '/community/comments',
        '/community/subscribers',
    ])->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();

    $this->actingAs($administrator);
    visit('/dashboard')
        ->on()->iPhone14Pro()
        ->inDarkMode()
        ->wait(1)
        ->assertSee('Author momentum')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

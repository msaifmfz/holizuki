<?php

use App\Domain\Community\Actions\StartSubscription;
use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Mail\SubscriberConfirmationMail;
use App\Domain\Community\Models\Comment;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Community\Support\SubscriberIdentity;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

test('a newsletter subscription stores encrypted email and queues confirmation', function (): void {
    Mail::fake();

    $this->post(route('newsletter.subscribe'), [
        'email' => 'Reader@Example.com',
        'source_location' => 'footer',
        'consent_version' => config('community.consent_version'),
    ])->assertRedirect();

    $subscriber = NewsletterSubscriber::query()->sole();
    $rawEmail = DB::table((new NewsletterSubscriber)->getTable())->value('email');

    expect($subscriber->email)->toBe('reader@example.com')
        ->and($rawEmail)->not->toBe('reader@example.com')
        ->and($subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->confirmation_sent_at?->diffInHours($subscriber->confirmation_expires_at))->toBe(48.0);
    Mail::assertQueued(SubscriberConfirmationMail::class);
});

test('reconfirming a pending address is idempotent', function (): void {
    Mail::fake();
    $startSubscription = resolve(StartSubscription::class);

    $first = $startSubscription->handle('same@example.com');
    $second = $startSubscription->handle('SAME@example.com');

    expect($first->subscriber->id)->toBe($second->subscriber->id)
        ->and($first->confirmationQueued)->toBeTrue()
        ->and($second->confirmationQueued)->toBeFalse()
        ->and(NewsletterSubscriber::query()->count())->toBe(1);
    Mail::assertQueued(SubscriberConfirmationMail::class, 1);
});

test('an expired pending address receives a fresh confirmation on resubmission', function (): void {
    Mail::fake();
    $identity = resolve(SubscriberIdentity::class);
    $subscriber = NewsletterSubscriber::factory()->create([
        'email_hash' => $identity->hash('expired@example.com'),
        'email' => 'expired@example.com',
        'confirmation_expires_at' => now()->subSecond(),
    ]);
    $oldTokenHash = $subscriber->confirmation_token_hash;

    $result = resolve(StartSubscription::class)->handle('expired@example.com');

    expect($result->subscriber->id)->toBe($subscriber->id)
        ->and($result->confirmationQueued)->toBeTrue()
        ->and($result->subscriber->confirmation_token_hash)->not->toBe($oldTokenHash)
        ->and($result->subscriber->confirmation_expires_at?->isFuture())->toBeTrue();
    Mail::assertQueued(SubscriberConfirmationMail::class, 1);
});

test('confirmation token GET is inert and POST confirms once', function (): void {
    $token = str_repeat('a', 64);
    $subscriber = NewsletterSubscriber::factory()->create([
        'confirmation_token_hash' => hash('sha256', $token),
        'confirmation_expires_at' => now()->addHours(48),
    ]);

    $this->get(route('newsletter.confirm.show', $token))->assertOk();
    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Pending);

    $this->post(route('newsletter.confirm.store', $token))
        ->assertRedirect(route('newsletter.confirmed'));

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Confirmed)
        ->and($subscriber->confirmed_at)->not->toBeNull()
        ->and($subscriber->confirmation_token_hash)->toBeNull();
});

test('expired confirmation tokens do not mutate subscribers', function (): void {
    $token = str_repeat('b', 64);
    $subscriber = NewsletterSubscriber::factory()->create([
        'confirmation_token_hash' => hash('sha256', $token),
        'confirmation_expires_at' => now()->subSecond(),
    ]);

    $this->get(route('newsletter.confirm.show', $token))->assertNotFound();
    $this->post(route('newsletter.confirm.store', $token))->assertNotFound();

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Pending);
});

test('unsubscribe token GET is inert and POST erases the encrypted email', function (): void {
    $token = str_repeat('c', 64);
    $subscriber = NewsletterSubscriber::factory()->confirmed()->create([
        'unsubscribe_token_hash' => hash('sha256', $token),
    ]);
    $emailHash = $subscriber->email_hash;

    $this->get(route('newsletter.unsubscribe.show', $token))->assertOk();
    expect($subscriber->refresh()->email)->not->toBeNull();

    $this->post(route('newsletter.unsubscribe.store', $token))
        ->assertRedirect(route('newsletter.unsubscribed'));

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->email)->toBeNull()
        ->and($subscriber->email_hash)->toBe($emailHash);
});

test('explicit resubscription after unsubscribe creates a fresh pending confirmation', function (): void {
    Mail::fake();
    $identity = resolve(SubscriberIdentity::class);
    $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create([
        'email_hash' => $identity->hash('return@example.com'),
    ]);

    $result = resolve(StartSubscription::class)->handle('return@example.com');

    expect($result->subscriber->id)->toBe($subscriber->id)
        ->and($result->subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($result->subscriber->email)->toBe('return@example.com')
        ->and($result->confirmationQueued)->toBeTrue();
});

test('administrators can filter resend unsubscribe and stream confirmed subscribers', function (): void {
    Mail::fake();
    $administrator = User::factory()->create();
    $identity = resolve(SubscriberIdentity::class);
    $confirmed = NewsletterSubscriber::factory()->confirmed()->create([
        'email' => 'confirmed@example.com',
        'email_hash' => $identity->hash('confirmed@example.com'),
        'source_location' => 'article_end',
    ]);
    $pending = NewsletterSubscriber::factory()->create([
        'email' => 'pending@example.com',
        'email_hash' => $identity->hash('pending@example.com'),
        'source_location' => 'footer',
    ]);

    $this->actingAs($administrator)
        ->get(route('community.subscribers.index', [
            'email' => 'CONFIRMED@example.com',
            'status' => SubscriberStatus::Confirmed->value,
            'source' => 'article_end',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.id', $confirmed->id));

    $this->actingAs($administrator)
        ->post(route('community.subscribers.resend', $pending))
        ->assertRedirect();
    Mail::assertQueued(SubscriberConfirmationMail::class);

    $export = $this->actingAs($administrator)
        ->get(route('community.subscribers.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($export->streamedContent())
        ->toContain('confirmed@example.com')
        ->not->toContain('pending@example.com');

    $this->actingAs($administrator)
        ->delete(route('community.subscribers.unsubscribe', $confirmed))
        ->assertRedirect();
    expect($confirmed->refresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($confirmed->email)->toBeNull();
});

test('community pruning applies subscriber and comment retention windows', function (): void {
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
    $pending = NewsletterSubscriber::factory()->create([
        'created_at' => now()->subDays(8),
        'confirmation_sent_at' => now()->subDays(8),
    ]);
    $rejected = Comment::factory()->rejected()->create([
        'rejected_at' => now()->subDays(91),
    ]);
    $deleted = Comment::factory()->create([
        'status' => CommentStatus::Deleted,
        'deleted_at' => now()->subDays(31),
        'body' => 'Erase after retention',
    ]);
    $approved = Comment::factory()->approved()->create();

    $this->artisan('community:prune')->assertSuccessful();

    expect(NewsletterSubscriber::query()->whereKey($pending)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($rejected)->exists())->toBeFalse()
        ->and($deleted->refresh()->body)->toBeNull()
        ->and($deleted->body_erased_at)->not->toBeNull()
        ->and(Comment::query()->whereKey($approved)->exists())->toBeTrue();
});

<?php

use App\Domain\Identity\Models\User;
use App\Domain\Inbox\Models\ContactSubmission;

/** @return array<string, string> */
function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'A Reader',
        'email' => 'reader@example.com',
        'subject' => 'Enjoyed the last post',
        'message' => 'Just wanted to say thanks for writing it.',
        ...$overrides,
    ];
}

test('a visitor can send a message through the contact form', function (): void {
    $this->from(route('public.contact.create'))
        ->post(route('public.contact.store'), contactPayload())
        ->assertRedirect(route('public.contact.create'));

    $submission = ContactSubmission::sole();
    expect($submission->name)->toBe('A Reader')
        ->and($submission->email)->toBe('reader@example.com')
        ->and($submission->message)->toBe('Just wanted to say thanks for writing it.')
        ->and($submission->ip_address)->not->toBeNull()
        ->and($submission->read_at)->toBeNull();
});

test('the contact form validates its input', function (array $overrides, string $field): void {
    $this->from(route('public.contact.create'))
        ->post(route('public.contact.store'), contactPayload($overrides))
        ->assertRedirect(route('public.contact.create'))
        ->assertSessionHasErrors($field);

    expect(ContactSubmission::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing message' => [['message' => ''], 'message'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
]);

test('a filled honeypot silently drops the submission', function (): void {
    $this->from(route('public.contact.create'))
        ->post(route('public.contact.store'), contactPayload(['company' => 'Totally Real Inc.']))
        ->assertRedirect(route('public.contact.create'))
        ->assertSessionDoesntHaveErrors();

    expect(ContactSubmission::count())->toBe(0);
});

test('contact submissions are rate limited per ip', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('public.contact.store'), contactPayload())->assertRedirect();
    }

    $this->post(route('public.contact.store'), contactPayload())->assertTooManyRequests();
    expect(ContactSubmission::count())->toBe(5);
});

test('administrators can read and manage the inbox', function (): void {
    $user = User::factory()->create();
    $older = ContactSubmission::factory()->read()->create(['name' => 'Old Sender']);
    $newest = ContactSubmission::factory()->create(['name' => 'New Sender']);

    $this->actingAs($user)
        ->get(route('contact-submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contact-submissions/index')
            ->has('submissions.data', 2)
            ->where('submissions.data.0.name', 'New Sender')
            ->where('unreadCount', 1));

    $this->actingAs($user)
        ->patch(route('contact-submissions.read', $newest))
        ->assertRedirect();
    expect($newest->refresh()->isRead())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('contact-submissions.destroy', $older))
        ->assertRedirect();
    expect(ContactSubmission::count())->toBe(1);
});

test('guests cannot access the inbox', function (): void {
    $this->get(route('contact-submissions.index'))->assertRedirect(route('login'));
});

test('an array honeypot value is treated as a bot submission', function (): void {
    $this->from(route('public.contact.create'))
        ->post(route('public.contact.store'), contactPayload(['company' => ['Totally Real Inc.']]))
        ->assertRedirect(route('public.contact.create'))
        ->assertSessionDoesntHaveErrors();

    expect(ContactSubmission::count())->toBe(0);
});

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<NewsletterSubscriber> */
class NewsletterSubscriberFactory extends Factory
{
    #[Override]
    protected $model = NewsletterSubscriber::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $email = Str::lower(fake()->unique()->safeEmail());
        $hashKey = config()->string('community.email_hash_key');

        return [
            'email' => $email,
            'email_hash' => hash_hmac('sha256', $email, $hashKey),
            'status' => SubscriberStatus::Pending,
            'confirmation_token_hash' => hash('sha256', Str::random(64)),
            'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
            'source_post_id' => null,
            'source_method' => 'form',
            'source_location' => 'footer',
            'source_content_key' => null,
            'consent_version' => config()->string('community.consent_version'),
            'confirmation_sent_at' => now(),
            'confirmation_expires_at' => now()->addHours(48),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'erased_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriberStatus::Confirmed,
            'confirmation_token_hash' => null,
            'confirmed_at' => now(),
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (): array => [
            'email' => null,
            'status' => SubscriberStatus::Unsubscribed,
            'confirmation_token_hash' => null,
            'unsubscribe_token_hash' => null,
            'unsubscribed_at' => now(),
            'erased_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Mail\SubscriberConfirmationMail;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Community\Support\SubscriberIdentity;
use App\Domain\Community\ValueObjects\SubscriptionStart;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StartSubscription
{
    public function __construct(private readonly SubscriberIdentity $identity) {}

    public function handle(
        string $email,
        ?Post $sourcePost = null,
        string $sourceMethod = 'form',
        string $sourceLocation = 'footer',
        ?string $consentVersion = null,
        bool $forceResend = false,
    ): SubscriptionStart {
        $normalizedEmail = $this->identity->normalize($email);
        $emailHash = $this->identity->hash($normalizedEmail);
        $confirmationToken = Str::random(64);
        $unsubscribeToken = Str::random(64);

        $result = DB::transaction(function () use (
            $normalizedEmail,
            $emailHash,
            $confirmationToken,
            $unsubscribeToken,
            $sourcePost,
            $sourceMethod,
            $sourceLocation,
            $consentVersion,
            $forceResend,
        ): SubscriptionStart {
            $subscriber = NewsletterSubscriber::query()
                ->where('email_hash', $emailHash)
                ->lockForUpdate()
                ->first();

            if ($subscriber?->status === SubscriberStatus::Confirmed) {
                return new SubscriptionStart($subscriber, false);
            }

            if ($subscriber === null) {
                $subscriber = new NewsletterSubscriber(['email_hash' => $emailHash]);
            } elseif (
                $subscriber->status === SubscriberStatus::Pending
                && ! $subscriber->isConfirmationExpired()
                && ! $forceResend
            ) {
                return new SubscriptionStart($subscriber, false);
            }

            $subscriber->forceFill([
                'email' => $normalizedEmail,
                'status' => SubscriberStatus::Pending,
                'confirmation_token_hash' => $this->identity->tokenHash($confirmationToken),
                'unsubscribe_token_hash' => $this->identity->tokenHash($unsubscribeToken),
                'source_post_id' => $sourcePost?->id,
                'source_method' => $sourceMethod,
                'source_location' => $sourceLocation,
                'source_content_key' => $sourcePost instanceof Post ? 'post:'.$sourcePost->id : null,
                'consent_version' => $consentVersion ?? config()->string('community.consent_version'),
                'confirmation_sent_at' => now(),
                'confirmation_expires_at' => now()->addHours(config()->integer('community.confirmation_hours')),
                'confirmed_at' => null,
                'unsubscribed_at' => null,
                'erased_at' => null,
            ]);
            $subscriber->save();

            return new SubscriptionStart($subscriber, true);
        });

        if ($result->confirmationQueued) {
            Mail::to($normalizedEmail)->send(
                new SubscriberConfirmationMail($result->subscriber, $confirmationToken, $unsubscribeToken)->afterCommit(),
            );
        }

        return $result;
    }
}

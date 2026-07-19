<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Events\SubscriberConfirmed;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Community\Support\SubscriberIdentity;
use Illuminate\Support\Facades\DB;

class ConfirmSubscription
{
    public function __construct(private readonly SubscriberIdentity $identity) {}

    public function findValid(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('confirmation_token_hash', $this->identity->tokenHash($token))
            ->first();

        if ($subscriber === null || $subscriber->status !== SubscriberStatus::Pending || $subscriber->isConfirmationExpired()) {
            return null;
        }

        return $subscriber;
    }

    public function handle(string $token): ?NewsletterSubscriber
    {
        $subscriber = DB::transaction(function () use ($token): ?NewsletterSubscriber {
            $subscriber = NewsletterSubscriber::query()
                ->where('confirmation_token_hash', $this->identity->tokenHash($token))
                ->lockForUpdate()
                ->first();

            if ($subscriber === null || $subscriber->status !== SubscriberStatus::Pending || $subscriber->isConfirmationExpired()) {
                return null;
            }

            $subscriber->forceFill([
                'status' => SubscriberStatus::Confirmed,
                'confirmation_token_hash' => null,
                'confirmation_expires_at' => null,
                'confirmed_at' => now(),
            ])->save();

            return $subscriber->refresh();
        });

        if ($subscriber !== null) {
            $activeSubscriberCount = NewsletterSubscriber::query()
                ->where('status', SubscriberStatus::Confirmed)
                ->count();
            event(new SubscriberConfirmed(
                $subscriber->id,
                $subscriber->source_content_key,
                $activeSubscriberCount,
            ));
        }

        return $subscriber;
    }
}

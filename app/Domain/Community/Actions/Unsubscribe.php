<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Community\Support\SubscriberIdentity;
use Illuminate\Support\Facades\DB;

class Unsubscribe
{
    public function __construct(private readonly SubscriberIdentity $identity) {}

    public function find(string $token): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::query()
            ->where('unsubscribe_token_hash', $this->identity->tokenHash($token))
            ->first();
    }

    public function handle(string $token): bool
    {
        return DB::transaction(function () use ($token): bool {
            $subscriber = NewsletterSubscriber::query()
                ->where('unsubscribe_token_hash', $this->identity->tokenHash($token))
                ->lockForUpdate()
                ->first();

            if ($subscriber === null) {
                return false;
            }

            $subscriber->forceFill([
                'email' => null,
                'status' => SubscriberStatus::Unsubscribed,
                'confirmation_token_hash' => null,
                'unsubscribe_token_hash' => null,
                'confirmation_expires_at' => null,
                'unsubscribed_at' => now(),
                'erased_at' => now(),
            ])->save();

            return true;
        });
    }
}

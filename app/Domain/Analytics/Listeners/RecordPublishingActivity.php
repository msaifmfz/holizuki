<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Actions\RecordFirstPublication;
use App\Domain\Analytics\Models\AuthorActivityEvent;
use App\Domain\Publishing\Events\PostContentUpdated;
use App\Domain\Publishing\Events\PostPublished;
use Carbon\CarbonImmutable;

class RecordPublishingActivity
{
    public function __construct(private readonly RecordFirstPublication $recordFirstPublication) {}

    public function handle(PostContentUpdated|PostPublished $event): void
    {
        if ($event instanceof PostPublished) {
            $this->recordFirstPublication->handle($event->post);

            return;
        }

        $post = $event->post;
        $publishedAt = $post->published_at;
        $now = CarbonImmutable::now(config()->string('app.timezone'));

        if ($publishedAt === null || CarbonImmutable::instance($publishedAt)->isSameWeek($now)) {
            return;
        }

        $weekKey = $now->isoWeekYear.'-'.str_pad((string) $now->isoWeek, 2, '0', STR_PAD_LEFT);
        AuthorActivityEvent::query()->firstOrCreate(
            ['event_key' => "post-maintained:{$post->id}:{$weekKey}"],
            [
                'user_id' => $post->updated_by_id ?? $post->author_id,
                'post_id' => $post->id,
                'event_id' => 'post_maintained',
                'metadata' => ['iso_week' => $weekKey],
                'occurred_at' => $now,
                'expires_at' => $now->addMonths(24),
            ],
        );
    }
}

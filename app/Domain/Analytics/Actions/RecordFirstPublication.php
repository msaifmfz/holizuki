<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorActivityEvent;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

class RecordFirstPublication
{
    public function __construct(private readonly MaterializeGoalPeriod $materialize) {}

    public function handle(Post $post): void
    {
        $publishedAt = $post->published_at === null
            ? CarbonImmutable::now(config()->string('app.timezone'))
            : CarbonImmutable::instance($post->published_at);
        $publication = AuthorPublication::query()->firstOrCreate(
            ['post_id' => $post->id],
            ['author_id' => $post->author_id, 'first_published_at' => $publishedAt],
        );

        if (! $publication->wasRecentlyCreated) {
            return;
        }

        AuthorActivityEvent::query()->firstOrCreate(
            ['event_key' => 'first-publication:'.$post->id],
            [
                'user_id' => $post->author_id,
                'post_id' => $post->id,
                'event_id' => 'post_published',
                'metadata' => ['initial_publication' => true],
                'occurred_at' => $publishedAt,
                'expires_at' => $publishedAt->addMonths(24),
            ],
        );

        if ($post->author_id === null) {
            return;
        }

        $goal = AuthorGoal::query()
            ->where('user_id', $post->author_id)
            ->whereDate('effective_from', '<=', $publishedAt->toDateString())
            ->where(function (Builder $query) use ($publishedAt): void {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $publishedAt->toDateString());
            })
            ->latest('effective_from')
            ->first();

        if ($goal === null) {
            return;
        }

        $period = $this->materialize->handle($goal, $publishedAt);
        if ($period->status !== GoalPeriodStatus::Paused) {
            $period->increment('published_count');
        }
    }
}

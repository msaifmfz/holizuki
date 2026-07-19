<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Community\Events\CommentApproved;
use App\Domain\Community\Events\SubscriberConfirmed;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;

class EvaluateCommunityMilestones
{
    /** @return Collection<int, AnalyticsMilestone> */
    public function handle(User $user, CommentApproved|SubscriberConfirmed $event): Collection
    {
        if ($event instanceof CommentApproved) {
            return collect([
                $this->record($user, 'first_approved_comment', [
                    'comment_id' => $event->commentId,
                    'post_id' => $event->postId,
                ]),
            ]);
        }

        $achieved = [];
        if ($event->activeSubscriberCount >= 1) {
            $achieved[] = $this->record($user, 'first_confirmed_subscriber', [
                'subscribers' => $event->activeSubscriberCount,
            ]);
        }
        if ($event->activeSubscriberCount >= 100) {
            $achieved[] = $this->record($user, 'confirmed_subscribers_100', [
                'subscribers' => $event->activeSubscriberCount,
            ]);
        }

        return collect($achieved);
    }

    /** @param array<string, int> $evidence */
    private function record(User $user, string $code, array $evidence): AnalyticsMilestone
    {
        return AnalyticsMilestone::query()->firstOrCreate(
            ['code' => $code, 'scope_key' => 'site'],
            ['user_id' => $user->id, 'evidence' => $evidence, 'achieved_at' => now()],
        );
    }
}

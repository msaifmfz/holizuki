<?php

declare(strict_types=1);

namespace App\Domain\Community\Console;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Community\Models\NewsletterSubscriber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('community:prune')]
#[Description('Prune expired community data according to the retention policy')]
class PruneCommunityData extends Command
{
    public function handle(): int
    {
        $unconfirmedResult = NewsletterSubscriber::query()
            ->where('status', SubscriberStatus::Pending)
            ->where('confirmation_sent_at', '<', now()->subDays(config()->integer('community.unconfirmed_retention_days')))
            ->delete();

        $rejectedResult = Comment::query()
            ->where('status', CommentStatus::Rejected)
            ->where('rejected_at', '<', now()->subDays(config()->integer('community.rejected_comment_retention_days')))
            ->delete();

        $erasedBodiesResult = Comment::query()
            ->where('status', CommentStatus::Deleted)
            ->whereNotNull('body')
            ->where('deleted_at', '<', now()->subDays(config()->integer('community.deleted_comment_body_retention_days')))
            ->update([
                'body' => null,
                'body_erased_at' => now(),
            ]);
        $unconfirmed = is_int($unconfirmedResult) ? $unconfirmedResult : 0;
        $rejected = is_int($rejectedResult) ? $rejectedResult : 0;
        $erasedBodies = $erasedBodiesResult;

        $this->components->info("Pruned {$unconfirmed} unconfirmed subscribers, {$rejected} rejected comments, and {$erasedBodies} deleted comment bodies.");

        return self::SUCCESS;
    }
}

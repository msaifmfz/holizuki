<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Console;

use App\Domain\Analytics\Actions\EvaluateMilestones;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:evaluate-milestones')]
#[Description('Evaluate insert-once publishing, audience, engagement, community, and longevity milestones')]
class EvaluateAnalyticsMilestones extends Command
{
    public function handle(EvaluateMilestones $evaluate): int
    {
        $count = 0;
        User::query()->where('role', UserRole::Administrator)->each(function (User $user) use ($evaluate, &$count): void {
            $count += $evaluate->handle($user)->count();
        });
        $this->components->info("Evaluated {$count} achieved milestone(s).");

        return self::SUCCESS;
    }
}

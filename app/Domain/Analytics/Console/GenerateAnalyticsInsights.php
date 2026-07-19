<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Console;

use App\Domain\Analytics\Actions\GenerateInsights;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:generate-insights')]
#[Description('Evaluate measured post evidence and persist eligible recommendations')]
class GenerateAnalyticsInsights extends Command
{
    public function handle(GenerateInsights $generate): int
    {
        $count = 0;
        User::query()->where('role', UserRole::Administrator)->each(function (User $user) use ($generate, &$count): void {
            $count += $generate->handle($user)->count();
        });
        $this->components->info("Generated {$count} evidence-backed insight(s).");

        return self::SUCCESS;
    }
}

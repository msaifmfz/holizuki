<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;

/**
 * Assumes a single-administrator blog: iterates every verified administrator,
 * but insights and milestones use global unique keys (no per-user scoping), so
 * with multiple administrators attribution goes to whichever refresh runs
 * first. Revisit key scoping before adding a second administrator.
 */
class RefreshAuthorMotivation
{
    public function __construct(
        private readonly CalculateMomentum $momentum,
        private readonly GenerateInsights $insights,
        private readonly EvaluateMilestones $milestones,
    ) {}

    public function handle(): void
    {
        User::query()
            ->where('role', UserRole::Administrator)
            ->whereNotNull('email_verified_at')
            ->each(function (User $user): void {
                $this->momentum->handle($user);
                $this->insights->handle($user);
                $this->milestones->handle($user);
            });
    }
}

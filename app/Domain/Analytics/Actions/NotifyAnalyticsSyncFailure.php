<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\SyncStatus;
use App\Domain\Analytics\Mail\AnalyticsSyncFailureMail;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class NotifyAnalyticsSyncFailure
{
    public function handle(string $sanitizedError): void
    {
        $statuses = AnalyticsSyncRun::query()
            ->where('attempt', 1)
            ->latest('started_at')
            ->latest('id')
            ->limit(3)
            ->pluck('status');
        $threeFailures = $statuses->count() === 3
            && $statuses->every(static fn (SyncStatus|string $status): bool => ($status instanceof SyncStatus ? $status : SyncStatus::from($status)) === SyncStatus::Failed);

        if (! $threeFailures || ! Cache::add('analytics:failure-notification', true, now()->addDay())) {
            return;
        }

        User::query()
            ->where('role', UserRole::Administrator)
            ->whereNotNull('email_verified_at')
            ->each(static function (User $user) use ($sanitizedError): void {
                Mail::to($user)->queue(new AnalyticsSyncFailureMail($sanitizedError));
            });
    }
}

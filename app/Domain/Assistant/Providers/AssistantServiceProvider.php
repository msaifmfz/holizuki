<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Providers;

use App\Domain\Assistant\Console\AssistantDoctorCommand;
use App\Domain\Assistant\Console\PruneAssistantDataCommand;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AuthoringContextCache;
use App\Domain\Assistant\Services\BlockDiffer;
use App\Domain\Assistant\Services\ClaudeCliRunner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Override;

class AssistantServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(AgentRunner::class, ClaudeCliRunner::class);
        // One cache per request or queued job, shared by every collaborator
        // that needs an authoring context during it.
        $this->app->scoped(AuthoringContextCache::class);
        $this->app->bind(
            BlockDiffer::class,
            fn (): BlockDiffer => new BlockDiffer(
                config()->integer('assistant.limits.diff_blocks', 500),
            ),
        );
    }

    public function boot(): void
    {
        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute(
            config()->integer('assistant.rate_limit', 30),
        )->by((string) ($request->user()->id ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([
                AssistantDoctorCommand::class,
                PruneAssistantDataCommand::class,
            ]);
        }
    }
}

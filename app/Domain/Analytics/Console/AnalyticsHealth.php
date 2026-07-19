<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Console;

use App\Domain\Analytics\Actions\CheckAnalyticsHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:health')]
#[Description('Validate Google Analytics credentials, identifiers, privacy configuration, and timezone')]
class AnalyticsHealth extends Command
{
    public function handle(CheckAnalyticsHealth $health): int
    {
        $result = $health->handle();

        foreach ($result->errors as $error) {
            $this->components->error($error);
        }
        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        if (! $result->healthy) {
            return self::FAILURE;
        }

        $this->components->info('Google Analytics configuration is healthy.');

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Console;

use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\ClaudeRuntimeInspector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Verify the server-side Claude Code runtime and OAuth authentication')]
#[Signature('assistant:doctor')]
class AssistantDoctorCommand extends Command
{
    public function handle(ClaudeRuntimeInspector $inspector, AssistantAvailability $availability): int
    {
        $health = $inspector->inspect();

        if (! $health->healthy) {
            foreach ($health->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // A verified-healthy runtime is the recovery path for a latched
        // permanent failure (e.g. an auth blip that marked the assistant
        // unavailable). Clear that without disturbing a live cooldown.
        $availability->clearBlockingFailure();

        $this->info('Claude Code '.$health->version.' is ready with subscription OAuth.');

        return self::SUCCESS;
    }
}

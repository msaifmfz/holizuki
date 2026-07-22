<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Console;

use App\Domain\Assistant\Actions\PruneAssistantData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Prune old assistant turns and orphaned AI workspaces')]
#[Signature('assistant:prune')]
class PruneAssistantDataCommand extends Command
{
    public function handle(PruneAssistantData $prune): int
    {
        $pruned = $prune->handle();

        $this->info("Pruned {$pruned} assistant records and workspaces.");

        return self::SUCCESS;
    }
}

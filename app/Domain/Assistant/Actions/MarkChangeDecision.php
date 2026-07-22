<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Models\AssistantChange;

class MarkChangeDecision
{
    public function handle(AssistantChange $change, AssistantChangeStatus $status): AssistantChange
    {
        $change->forceFill(['status' => $status, 'decided_at' => now()])->save();

        return $change;
    }
}

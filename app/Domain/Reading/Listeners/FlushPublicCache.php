<?php

declare(strict_types=1);

namespace App\Domain\Reading\Listeners;

use App\Domain\Reading\Support\PublicCache;

class FlushPublicCache
{
    public function handle(): void
    {
        PublicCache::flush();
    }
}

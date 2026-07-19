<?php

declare(strict_types=1);

use App\Domain\Publishing\Providers\PublishingServiceProvider;
use App\Domain\Reading\Providers\ReadingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    PublishingServiceProvider::class,
    ReadingServiceProvider::class,
];

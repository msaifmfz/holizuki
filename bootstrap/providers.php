<?php

declare(strict_types=1);

use App\Domain\Analytics\Providers\AnalyticsServiceProvider;
use App\Domain\Community\Providers\CommunityServiceProvider;
use App\Domain\Publishing\Providers\PublishingServiceProvider;
use App\Domain\Reading\Providers\ReadingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    CommunityServiceProvider::class,
    AnalyticsServiceProvider::class,
    PublishingServiceProvider::class,
    ReadingServiceProvider::class,
];

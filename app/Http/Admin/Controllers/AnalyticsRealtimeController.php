<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Analytics\Actions\RealtimeAnalytics;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

class AnalyticsRealtimeController extends Controller
{
    public function __invoke(RealtimeAnalytics $realtime): JsonResponse
    {
        abort_unless(config()->boolean('analytics.dashboard_enabled'), 404);

        return response()->json($realtime->handle());
    }
}

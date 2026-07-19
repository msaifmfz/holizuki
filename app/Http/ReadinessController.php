<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(): Response
    {
        try {
            DB::connection()->select('select 1');
            Cache::store()->get('health:readiness');
        } catch (Throwable) {
            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response('', Response::HTTP_NO_CONTENT, [
            'X-Holizuki-Release' => Config::string('deployment.release'),
        ]);
    }
}

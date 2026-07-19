<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [];

        if (config('blog.ai_crawlers.policy') === 'block') {
            $bots = config()->array('blog.ai_crawlers.bots', []);

            foreach ($bots as $bot) {
                if (! is_string($bot)) {
                    continue;
                }

                $lines[] = 'User-agent: '.$bot;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }
        }

        $lines[] = 'User-agent: *';
        $lines[] = 'Disallow:';
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('public.sitemap');

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

<?php

test('robots.txt allows everything and lists the sitemap by default', function (): void {
    $response = $this->get('/robots.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    $body = $response->getContent();
    expect($body)->toContain("User-agent: *\nDisallow:")
        ->toContain('Sitemap: '.route('public.sitemap'))
        ->not->toContain('Disallow: /');
});

test('the block policy disallows configured ai crawlers while keeping search engines', function (): void {
    config(['blog.ai_crawlers.policy' => 'block']);

    $body = $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)->toContain("User-agent: GPTBot\nDisallow: /")
        ->toContain("User-agent: ClaudeBot\nDisallow: /")
        ->toContain("User-agent: *\nDisallow:\n")
        ->toContain('Sitemap: '.route('public.sitemap'));
});

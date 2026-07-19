<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Crawlers and social scrapers only see the first full page load, so
             SEO meta is rendered server-side from the page's `seo` prop. --}}
        @isset($page['props']['seo'])
            @php($seo = $page['props']['seo'])
            <meta name="description" content="{{ $seo['description'] }}">
            @isset($seo['robots'])
                <meta name="robots" content="{{ $seo['robots'] }}">
            @endisset
            @isset($seo['canonical'])
                <link rel="canonical" href="{{ $seo['canonical'] }}">
                <meta property="og:url" content="{{ $seo['canonical'] }}">
            @endisset
            @isset($seo['prev_url'])
                <link rel="prev" href="{{ $seo['prev_url'] }}">
            @endisset
            @isset($seo['next_url'])
                <link rel="next" href="{{ $seo['next_url'] }}">
            @endisset
            <meta property="og:site_name" content="{{ config('app.name', 'Holizuki') }}">
            <meta property="og:title" content="{{ $seo['og_title'] ?? $seo['title'] }}">
            <meta property="og:description" content="{{ $seo['og_description'] ?? $seo['description'] }}">
            <meta property="og:type" content="{{ $seo['type'] }}">
            @isset($seo['image'])
                <meta property="og:image" content="{{ $seo['image'] }}">
            @endisset
            @isset($seo['published_time'])
                <meta property="article:published_time" content="{{ $seo['published_time'] }}">
            @endisset
            @isset($seo['modified_time'])
                <meta property="article:modified_time" content="{{ $seo['modified_time'] }}">
            @endisset
            @isset($seo['author'])
                <meta name="author" content="{{ $seo['author'] }}">
            @endisset
            <meta name="twitter:card" content="{{ isset($seo['image']) ? 'summary_large_image' : 'summary' }}">
            <meta name="twitter:title" content="{{ $seo['og_title'] ?? $seo['title'] }}">
            <meta name="twitter:description" content="{{ $seo['og_description'] ?? $seo['description'] }}">
            @isset($seo['json_ld'])
                <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
            @endisset
        @endisset
        <link rel="alternate" type="application/rss+xml" title="{{ config('app.name', 'Holizuki') }}" href="{{ url('/feed') }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Holizuki') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name') }}</title>
        <link>{{ route('home') }}</link>
        <atom:link href="{{ route('public.feed') }}" rel="self" type="application/rss+xml" />
        <description>{{ $description }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
@foreach ($items as $item)
        <item>
            <title>{{ $item['title'] }}</title>
            <link>{{ $item['url'] }}</link>
            <guid isPermaLink="true">{{ $item['url'] }}</guid>
            <pubDate>{{ $item['published_at'] }}</pubDate>
@isset ($item['author'])
            <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $item['author'] }}</dc:creator>
@endisset
@isset ($item['excerpt'])
            <description>{{ $item['excerpt'] }}</description>
@endisset
        </item>
@endforeach
    </channel>
</rss>

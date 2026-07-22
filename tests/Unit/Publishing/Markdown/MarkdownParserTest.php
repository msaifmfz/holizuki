<?php

use App\Domain\Publishing\Markdown\MarkdownParser;

function parseDocument(string $markdown, array $media = []): array
{
    return new MarkdownParser()->parse($markdown, $media)->toArray();
}

test('an empty string parses to an empty document', function (): void {
    expect(parseDocument(''))->toBe(['type' => 'doc', 'content' => []]);
});

test('block constructs parse to whitelisted nodes', function (string $markdown, array $expected): void {
    expect(parseDocument($markdown))->toEqual(['type' => 'doc', 'content' => $expected]);
})->with([
    'paragraph' => [
        'Hello world',
        [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello world']]]],
    ],
    'heading levels clamp into 2-3' => [
        "# Too big\n\n#### Too small",
        [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Too big']]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Too small']]],
        ],
    ],
    'bullet list' => [
        "- One\n- Two",
        [['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]]]],
        ]]],
    ],
    'ordered list keeps its start' => [
        "5. Five\n6. Six",
        [['type' => 'orderedList', 'attrs' => ['start' => 5], 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Five']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Six']]]]],
        ]]],
    ],
    'blockquote' => [
        '> Wisdom',
        [['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Wisdom']]]]]],
    ],
    'fenced code with a known language' => [
        "```php\necho 1;\n```",
        [['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => 'echo 1;']]]],
    ],
    'fenced code with an unknown language becomes plaintext' => [
        "```klingon\nqapla\n```",
        [['type' => 'codeBlock', 'attrs' => ['language' => 'plaintext'], 'content' => [['type' => 'text', 'text' => 'qapla']]]],
    ],
    'thematic break' => [
        '---',
        [['type' => 'horizontalRule']],
    ],
]);

test('inline constructs parse to whitelisted marks', function (string $markdown, array $expected): void {
    expect(parseDocument($markdown))->toEqual(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $expected]]]);
})->with([
    'bold' => ['**bold**', [['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]]]],
    'italic' => ['*italic*', [['type' => 'text', 'text' => 'italic', 'marks' => [['type' => 'italic']]]]],
    'strike' => ['~~gone~~', [['type' => 'text', 'text' => 'gone', 'marks' => [['type' => 'strike']]]]],
    'underline via u tags' => ['<u>under</u>', [['type' => 'text', 'text' => 'under', 'marks' => [['type' => 'underline']]]]],
    'inline code' => ['`run()`', [['type' => 'text', 'text' => 'run()', 'marks' => [['type' => 'code']]]]],
    'link' => [
        '[docs](https://example.com)',
        [['type' => 'text', 'text' => 'docs', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]]],
    ],
    'nested marks sort canonically' => [
        '***both***',
        [['type' => 'text', 'text' => 'both', 'marks' => [['type' => 'bold'], ['type' => 'italic']]]],
    ],
    'underline spanning other marks' => [
        '<u>plain **bold**</u>',
        [
            ['type' => 'text', 'text' => 'plain ', 'marks' => [['type' => 'underline']]],
            ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold'], ['type' => 'underline']]],
        ],
    ],
    'soft breaks join as spaces' => [
        "one\ntwo",
        [['type' => 'text', 'text' => 'one two']],
    ],
    'hard breaks become nodes' => [
        "one\\\ntwo",
        [
            ['type' => 'text', 'text' => 'one'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'two'],
        ],
    ],
]);

test('unknown html is stripped while its text survives', function (): void {
    expect(parseDocument('<span class="x">kept</span> <script>alert(1)</script>'))
        ->toEqual(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'kept alert(1)']]]]]);
});

test('media images resolve against the provided media map', function (): void {
    $document = parseDocument('![A sunset](media:12 "Golden hour")', [
        12 => ['src' => '/storage/posts/1/inline/x.webp', 'width' => 800, 'height' => 600],
    ]);

    expect($document['content'])->toEqual([[
        'type' => 'image',
        'attrs' => [
            'mediaId' => 12,
            'src' => '/storage/posts/1/inline/x.webp',
            'alt' => 'A sunset',
            'caption' => 'Golden hour',
            'width' => 800,
            'height' => 600,
        ],
    ]]);
});

test('images are dropped when unresolvable', function (string $markdown): void {
    expect(parseDocument($markdown, [12 => ['src' => '/s.webp', 'width' => 1, 'height' => 1]])['content'])->toBe([]);
})->with([
    'foreign url' => ['![x](https://example.com/x.jpg)'],
    'unknown media id' => ['![x](media:999)'],
    'malformed media reference' => ['![x](media:abc)'],
]);

test('an image between text splits the paragraph around it', function (): void {
    $document = parseDocument('before ![x](media:12) after', [
        12 => ['src' => '/s.webp', 'width' => 10, 'height' => 10],
    ]);

    expect(array_column($document['content'], 'type'))->toBe(['paragraph', 'image', 'paragraph']);
});

test('nested list structures survive parsing', function (): void {
    $document = parseDocument("- Parent\n\n  - Child");

    expect($document['content'])->toEqual([[
        'type' => 'bulletList',
        'content' => [[
            'type' => 'listItem',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Parent']]],
                ['type' => 'bulletList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Child']]]],
                ]]],
            ],
        ]],
    ]]);
});

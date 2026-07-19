<?php

use App\Domain\Publishing\ValueObjects\RichTextDocument;

test('plain text extraction includes nested prose headings and code', function (): void {
    $document = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Queue workers']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Retry failed jobs '],
                ['type' => 'text', 'text' => 'safely', 'marks' => [['type' => 'strong']]],
            ]],
            ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => 'dispatch($job);']]],
        ],
    ];

    $text = RichTextDocument::fromArray($document)->plainText();

    expect($text)
        ->toContain('Queue workers')
        ->toContain('Retry failed jobs safely')
        ->toContain('dispatch($job);');
});

test('reading time is at least one minute and rounds partial minutes up', function (int $wordCount, int $expected): void {
    $document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => trim(str_repeat('word ', $wordCount))]],
        ]],
    ];

    expect(RichTextDocument::fromArray($document)->readingTime())->toBe($expected);
})->with([
    'empty document' => [0, 1],
    'one word' => [1, 1],
    'one full minute' => [225, 1],
    'a partial second minute' => [226, 2],
]);

test('referenced media ids are returned once in document order', function (): void {
    $document = [
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => ['mediaId' => 17, 'alt' => 'First image']],
            ['type' => 'image', 'attrs' => ['mediaId' => 23, 'alt' => 'Second image']],
            ['type' => 'image', 'attrs' => ['mediaId' => 17, 'alt' => 'First image repeated']],
            ['type' => 'image', 'attrs' => ['src' => 'https://example.com/untrusted.jpg', 'alt' => 'Remote image']],
        ],
    ];

    expect(RichTextDocument::fromArray($document)->referencedMediaIds())->toBe([17, 23]);
});

test('reader documents assign duplicate-safe heading ids and resolve media on the server', function (): void {
    $document = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Café queues']]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Café queues']]],
            ['type' => 'image', 'attrs' => [
                'mediaId' => 17,
                'src' => 'https://untrusted.example/ignored.webp',
                'alt' => 'A queue diagram',
                'caption' => 'Queue topology',
                'width' => 1,
                'height' => 1,
            ]],
            ['type' => 'image', 'attrs' => [
                'mediaId' => 99,
                'src' => 'https://untrusted.example/missing.webp',
                'alt' => 'Missing media',
            ]],
        ],
    ];

    $reader = RichTextDocument::fromArray($document)->readerDocument([
        17 => [
            'url' => '/storage/posts/17/diagram.webp',
            'width' => 1600,
            'height' => 900,
        ],
    ]);

    expect($reader['table_of_contents'])->toBe([
        ['id' => 'cafe-queues', 'title' => 'Café queues', 'level' => 2],
        ['id' => 'cafe-queues-2', 'title' => 'Café queues', 'level' => 3],
    ])->and($reader['document']['content'])->toHaveCount(3)
        ->and($reader['document']['content'][0]['attrs']['id'])->toBe('cafe-queues')
        ->and($reader['document']['content'][1]['attrs']['id'])->toBe('cafe-queues-2')
        ->and($reader['document']['content'][2]['attrs'])->toBe([
            'mediaId' => 17,
            'src' => '/storage/posts/17/diagram.webp',
            'alt' => 'A queue diagram',
            'caption' => 'Queue topology',
            'width' => 1600,
            'height' => 900,
        ]);
});

test('only the reader-supported code languages are accepted', function (): void {
    expect(RichTextDocument::supportsCodeLanguage('php'))->toBeTrue()
        ->and(RichTextDocument::supportsCodeLanguage('typescript'))->toBeTrue()
        ->and(RichTextDocument::supportsCodeLanguage('brainfuck'))->toBeFalse()
        ->and(RichTextDocument::supportsCodeLanguage(null))->toBeFalse();
});

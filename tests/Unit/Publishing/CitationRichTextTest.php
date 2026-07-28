<?php

use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Markdown\MarkdownSerializer;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use App\Domain\Publishing\ValueObjects\RichTextDocument;

test('citation nodes only accept a positive source id and no embedded data', function (array $citation, bool $valid): void {
    $failures = [];
    $document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [$citation],
        ]],
    ];

    new ValidRichTextDocument()->validate(
        'body',
        $document,
        function (string $message) use (&$failures): void {
            $failures[] = $message;
        },
    );

    expect($failures === [])->toBe($valid);
})->with([
    'valid' => [
        ['type' => 'citation', 'attrs' => ['sourceId' => 12]],
        true,
    ],
    'string id' => [
        ['type' => 'citation', 'attrs' => ['sourceId' => '12']],
        false,
    ],
    'zero id' => [
        ['type' => 'citation', 'attrs' => ['sourceId' => 0]],
        false,
    ],
    'private path attribute' => [
        ['type' => 'citation', 'attrs' => ['sourceId' => 12, 'path' => '/private/research.pdf']],
        false,
    ],
    'embedded content' => [
        [
            'type' => 'citation',
            'attrs' => ['sourceId' => 12],
            'content' => [['type' => 'text', 'text' => 'untrusted']],
        ],
        false,
    ],
    'mark' => [
        [
            'type' => 'citation',
            'attrs' => ['sourceId' => 12],
            'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]],
        ],
        false,
    ],
]);

test('source references are returned once in first appearance order', function (): void {
    $document = RichTextDocument::fromArray([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'First'],
                ['type' => 'citation', 'attrs' => ['sourceId' => 17]],
                ['type' => 'citation', 'attrs' => ['sourceId' => 4]],
                ['type' => 'citation', 'attrs' => ['sourceId' => 17]],
                ['type' => 'citation', 'attrs' => ['sourceId' => 29]],
            ],
        ]],
    ]);

    expect($document->referencedSourceIds())->toBe([17, 4, 29]);
});

test('reader documents number deduplicated citations and drop unresolved references', function (): void {
    $document = RichTextDocument::fromArray([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'One'],
                ['type' => 'citation', 'attrs' => ['sourceId' => 17]],
                ['type' => 'text', 'text' => ' two'],
                ['type' => 'citation', 'attrs' => ['sourceId' => 4]],
                ['type' => 'citation', 'attrs' => ['sourceId' => 17]],
                ['type' => 'citation', 'attrs' => ['sourceId' => 999]],
            ],
        ]],
    ]);

    $reader = $document->readerDocument(sources: [
        4 => [
            'title' => 'Field notes',
            'kind' => 'note',
            'url' => null,
        ],
        17 => [
            'title' => 'Primary handbook',
            'kind' => 'url',
            'url' => 'https://example.com/handbook',
        ],
    ]);

    expect($reader['citations'])->toBe([
        [
            'number' => 1,
            'source_id' => 17,
            'title' => 'Primary handbook',
            'kind' => 'url',
            'url' => 'https://example.com/handbook',
        ],
        [
            'number' => 2,
            'source_id' => 4,
            'title' => 'Field notes',
            'kind' => 'note',
            'url' => null,
        ],
    ])->and($reader['document']['content'][0]['content'])->toBe([
        ['type' => 'text', 'text' => 'One'],
        ['type' => 'citation', 'attrs' => ['sourceId' => 17, 'number' => 1]],
        ['type' => 'text', 'text' => ' two'],
        ['type' => 'citation', 'attrs' => ['sourceId' => 4, 'number' => 2]],
        ['type' => 'citation', 'attrs' => ['sourceId' => 17, 'number' => 1]],
    ]);
});

test('citations use a deterministic markdown syntax and round trip', function (): void {
    $document = RichTextDocument::fromArray([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'The result'],
                ['type' => 'citation', 'attrs' => ['sourceId' => 42]],
                ['type' => 'text', 'text' => ' is reproducible.'],
            ],
        ]],
    ]);

    $markdown = new MarkdownSerializer()->serialize($document);
    $parsed = new MarkdownParser()->parse($markdown);

    expect($markdown)->toBe("The result[](source:42) is reproducible.\n")
        ->and($parsed->toArray())->toBe($document->toArray())
        ->and(new MarkdownSerializer()->serialize($parsed))->toBe($markdown);
});

test('a citation-only paragraph survives markdown round trip', function (): void {
    $parsed = new MarkdownParser()->parse('[](source:42)');

    expect($parsed->toArray())->toBe([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'citation',
                'attrs' => ['sourceId' => 42],
            ]],
        ]],
    ])->and(new MarkdownSerializer()->serialize($parsed))
        ->toBe("[](source:42)\n");
});

test('labeled or malformed source links do not become citations or links', function (): void {
    $labeled = new MarkdownParser()->parse('[visible](source:42)');
    $malformed = new MarkdownParser()->parse('[visible](source:nope)');

    expect($labeled->plainText())->toBe('visible')
        ->and($labeled->referencedSourceIds())->toBe([])
        ->and($labeled->toArray()['content'][0]['content'][0])->toBe([
            'type' => 'text',
            'text' => 'visible',
        ])
        ->and($malformed->toArray()['content'][0]['content'][0])->toBe([
            'type' => 'text',
            'text' => 'visible',
        ]);
});

test('citation-like literal text remains text after markdown round trip', function (): void {
    $document = RichTextDocument::fromArray([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => '[](source:42)',
            ]],
        ]],
    ]);

    $markdown = new MarkdownSerializer()->serialize($document);
    $parsed = new MarkdownParser()->parse($markdown);

    expect($markdown)->toBe("\\[\\](source:42)\n")
        ->and($parsed->plainText())->toBe('[](source:42)')
        ->and($parsed->referencedSourceIds())->toBe([]);
});

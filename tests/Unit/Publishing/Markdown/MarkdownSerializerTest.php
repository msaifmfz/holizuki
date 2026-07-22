<?php

use App\Domain\Publishing\Markdown\MarkdownSerializer;
use App\Domain\Publishing\ValueObjects\RichTextDocument;

function serializeDocument(array $content): string
{
    return new MarkdownSerializer()->serialize(RichTextDocument::fromArray(['type' => 'doc', 'content' => $content]));
}

test('an empty document serializes to an empty string', function (): void {
    expect(serializeDocument([]))->toBe('');
    expect(serializeDocument([['type' => 'paragraph']]))->toBe('');
});

test('block nodes serialize to their markdown forms', function (array $content, string $expected): void {
    expect(serializeDocument($content))->toBe($expected);
})->with([
    'paragraph' => [
        [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello world']]]],
        "Hello world\n",
    ],
    'headings' => [
        [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Section']]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Subsection']]],
        ],
        "## Section\n\n### Subsection\n",
    ],
    'bullet list' => [
        [['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]]]],
        ]]],
        "- One\n- Two\n",
    ],
    'ordered list with start' => [
        [['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Three']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Four']]]]],
        ]]],
        "3. Three\n4. Four\n",
    ],
    'blockquote' => [
        [['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Quoted']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Twice']]],
        ]]],
        "> Quoted\n>\n> Twice\n",
    ],
    'code block with language' => [
        [['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => "echo 'hi';"]]]],
        "```php\necho 'hi';\n```\n",
    ],
    'code block without language falls back to plaintext' => [
        [['type' => 'codeBlock', 'attrs' => ['language' => null], 'content' => [['type' => 'text', 'text' => 'raw']]]],
        "```plaintext\nraw\n```\n",
    ],
    'horizontal rule' => [
        [['type' => 'horizontalRule']],
        "---\n",
    ],
    'image with caption' => [
        [['type' => 'image', 'attrs' => ['mediaId' => 12, 'src' => '/storage/x.webp', 'alt' => 'A sunset', 'caption' => 'Golden hour', 'width' => 800, 'height' => 600]]],
        "![A sunset](media:12 \"Golden hour\")\n",
    ],
    'image without caption' => [
        [['type' => 'image', 'attrs' => ['mediaId' => 12, 'src' => '/storage/x.webp', 'alt' => 'A sunset', 'caption' => null, 'width' => null, 'height' => null]]],
        "![A sunset](media:12)\n",
    ],
]);

test('marks serialize with canonical nesting and delimiters', function (array $content, string $expected): void {
    expect(serializeDocument([['type' => 'paragraph', 'content' => $content]]))->toBe($expected."\n");
})->with([
    'bold' => [[['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]]], '**bold**'],
    'italic' => [[['type' => 'text', 'text' => 'italic', 'marks' => [['type' => 'italic']]]], '*italic*'],
    'strike' => [[['type' => 'text', 'text' => 'gone', 'marks' => [['type' => 'strike']]]], '~~gone~~'],
    'underline' => [[['type' => 'text', 'text' => 'under', 'marks' => [['type' => 'underline']]]], '<u>under</u>'],
    'inline code' => [[['type' => 'text', 'text' => 'run()', 'marks' => [['type' => 'code']]]], '`run()`'],
    'code containing backticks' => [[['type' => 'text', 'text' => 'a`b', 'marks' => [['type' => 'code']]]], '`` a`b ``'],
    'link' => [
        [['type' => 'text', 'text' => 'docs', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]]],
        '[docs](https://example.com)',
    ],
    'bold italic nests canonically regardless of stored order' => [
        [['type' => 'text', 'text' => 'both', 'marks' => [['type' => 'italic'], ['type' => 'bold']]]],
        '***both***',
    ],
    'link around bold' => [
        [['type' => 'text', 'text' => 'go', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]]],
        '[**go**](https://example.com)',
    ],
    'adjacent runs sharing an outer mark reuse one delimiter pair' => [
        [
            ['type' => 'text', 'text' => 'all ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'in', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
            ['type' => 'text', 'text' => ' one', 'marks' => [['type' => 'bold']]],
        ],
        '**all *in* one**',
    ],
]);

test('boundary whitespace moves outside emphasis delimiters', function (): void {
    $markdown = serializeDocument([[
        'type' => 'paragraph',
        'content' => [
            ['type' => 'text', 'text' => 'a'],
            ['type' => 'text', 'text' => ' padded ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'z'],
        ],
    ]]);

    expect($markdown)->toBe("a **padded** z\n");
});

test('hard breaks serialize as backslash newlines', function (): void {
    $markdown = serializeDocument([[
        'type' => 'paragraph',
        'content' => [
            ['type' => 'text', 'text' => 'line one'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'line two'],
        ],
    ]]);

    expect($markdown)->toBe("line one\\\nline two\n");
});

test('markdown syntax inside text is escaped', function (): void {
    $markdown = serializeDocument([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'not *emphasis* or `code` or <u>html</u>']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '# not a heading']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '1. not a list']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '- not a bullet']]],
    ]);

    expect($markdown)->toBe(
        "not \*emphasis\* or \`code\` or \<u\>html\</u\>\n\n".
        "\# not a heading\n\n".
        "1\. not a list\n\n".
        "\- not a bullet\n",
    );
});

test('nested lists indent under their parent item', function (): void {
    $markdown = serializeDocument([[
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

    expect($markdown)->toBe("- Parent\n\n  - Child\n");
});

test('images without a positive media id are dropped', function (): void {
    expect(serializeDocument([['type' => 'image', 'attrs' => ['src' => 'https://example.com/x.jpg', 'alt' => 'Remote']]]))->toBe('');
});

test('code block fences grow past embedded backtick runs', function (): void {
    $markdown = serializeDocument([[
        'type' => 'codeBlock',
        'attrs' => ['language' => 'markdown'],
        'content' => [['type' => 'text', 'text' => '``` not a fence ```']],
    ]]);

    expect($markdown)->toBe("````markdown\n``` not a fence ```\n````\n");
});

<?php

use App\Domain\Publishing\Markdown\MarkdownParser;
use App\Domain\Publishing\Markdown\MarkdownSerializer;
use App\Domain\Publishing\Rules\ValidRichTextDocument;
use App\Domain\Publishing\ValueObjects\RichTextDocument;

/**
 * The codec contract: serializing any valid document and parsing it back must
 * reach a fixpoint after one pass — serialize(parse(md)) === md — and every
 * parsed document must satisfy the ValidRichTextDocument whitelist.
 */
function assertRoundTripStable(array $content, array $media = []): void
{
    $serializer = new MarkdownSerializer;
    $parser = new MarkdownParser;

    $markdown = $serializer->serialize(RichTextDocument::fromArray(['type' => 'doc', 'content' => $content]));
    $reparsed = $parser->parse($markdown, $media);
    $secondPass = $serializer->serialize($reparsed);

    expect($secondPass)->toBe($markdown);

    $failures = [];
    new ValidRichTextDocument()->validate('body', $reparsed->toArray(), function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe([]);
}

$media = [
    7 => ['src' => '/storage/posts/1/inline/a.webp', 'width' => 1200, 'height' => 800],
    9 => ['src' => '/storage/posts/1/inline/b.webp', 'width' => 640, 'height' => 480],
];

test('documents round trip to a stable markdown fixpoint', function (array $content) use ($media): void {
    assertRoundTripStable($content, $media);
})->with([
    'empty document' => [[]],
    'plain paragraphs' => [[
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First paragraph.']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Second paragraph.']]],
    ]],
    'headings and sections' => [[
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Getting started']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Intro text.']]],
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Install & configure']]],
    ]],
    'every simple mark' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'plain '],
            ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'italic', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'struck', 'marks' => [['type' => 'strike']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'underlined', 'marks' => [['type' => 'underline']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'code()', 'marks' => [['type' => 'code']]],
        ]],
    ]],
    'nested and adjacent marks' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'all ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'in', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
            ['type' => 'text', 'text' => ' one', 'marks' => [['type' => 'bold']]],
        ]],
    ]],
    'links with and without nested marks' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'see ', 'marks' => []],
            ['type' => 'text', 'text' => 'the docs', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/docs']]]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'text', 'text' => 'bold link', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'https://example.com/two']]]],
        ]],
    ]],
    'mailto link' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'write me', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'mailto:hi@example.com']]]],
        ]],
    ]],
    'underline spanning mixed marks' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'plain ', 'marks' => [['type' => 'underline']]],
            ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold'], ['type' => 'underline']]],
        ]],
    ]],
    'hard breaks' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'line one'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'line two'],
        ]],
    ]],
    'markdown-hostile text' => [[
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '*stars* _underscores_ [brackets] `ticks` <tags> & ~tildes~ \\slashes\\']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '# leading hash']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '42. leading ordinal']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '- leading dash']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '> leading gt']]],
    ]],
    'unicode text' => [[
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Émojis 🎉 and «quotes» und Größe']]],
    ]],
    'bullet list with nesting' => [[
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Parent one']]],
                ['type' => 'bulletList', 'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Child']]]]],
                ]],
            ]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Parent two']]]]],
        ]],
    ]],
    'ordered list with custom start and multi-block items' => [[
        ['type' => 'orderedList', 'attrs' => ['start' => 4], 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Step four']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'With a follow-up paragraph.']]],
            ]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Step five']]]]],
        ]],
    ]],
    'blockquote with multiple blocks' => [[
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First quoted paragraph.']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Second quoted paragraph.']]],
        ]],
    ]],
    'code block containing markdown syntax' => [[
        ['type' => 'codeBlock', 'attrs' => ['language' => 'markdown'], 'content' => [['type' => 'text', 'text' => "# heading\n\n- list\n\n**bold**"]]],
    ]],
    'code block containing fence-like runs' => [[
        ['type' => 'codeBlock', 'attrs' => ['language' => 'plaintext'], 'content' => [['type' => 'text', 'text' => "```\nnested fence\n```"]]],
    ]],
    'php code block' => [[
        ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => "<?php\n\necho 'hello';"]]],
    ]],
    'horizontal rules between paragraphs' => [[
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Above.']]],
        ['type' => 'horizontalRule'],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Below.']]],
    ]],
    'images with and without captions' => [[
        ['type' => 'image', 'attrs' => ['mediaId' => 7, 'src' => '/storage/posts/1/inline/a.webp', 'alt' => 'Alpine lake', 'caption' => 'Taken at dawn', 'width' => 1200, 'height' => 800]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Between images.']]],
        ['type' => 'image', 'attrs' => ['mediaId' => 9, 'src' => '/storage/posts/1/inline/b.webp', 'alt' => 'Trail marker', 'caption' => null, 'width' => 640, 'height' => 480]],
    ]],
    'image alt with markdown characters' => [[
        ['type' => 'image', 'attrs' => ['mediaId' => 7, 'src' => '/storage/posts/1/inline/a.webp', 'alt' => 'A [bracketed] *starred* view', 'caption' => 'With "quotes" too', 'width' => 1200, 'height' => 800]],
    ]],
    'inline code with backticks and boundary spaces' => [[
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'run '],
            ['type' => 'text', 'text' => '`quoted`', 'marks' => [['type' => 'code']]],
        ]],
    ]],
    'a full article shape' => [[
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Why queues matter']]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Queues let you '],
            ['type' => 'text', 'text' => 'defer work', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' out of the request cycle. See '],
            ['type' => 'text', 'text' => 'the docs', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://laravel.com/docs/queues']]]],
            ['type' => 'text', 'text' => '.'],
        ]],
        ['type' => 'image', 'attrs' => ['mediaId' => 7, 'src' => '/storage/posts/1/inline/a.webp', 'alt' => 'Queue dashboard', 'caption' => null, 'width' => 1200, 'height' => 800]],
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Setup']]],
        ['type' => 'orderedList', 'attrs' => ['start' => 1], 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Configure the connection.']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Run the worker.']]]]],
        ]],
        ['type' => 'codeBlock', 'attrs' => ['language' => 'bash'], 'content' => [['type' => 'text', 'text' => 'php artisan queue:work']]],
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Always set a timeout.']]],
        ]],
        ['type' => 'horizontalRule'],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Fin.']]],
    ]],
]);

test('parsing markdown the serializer never produced still yields valid documents', function (string $markdown) use ($media): void {
    $parsed = new MarkdownParser()->parse($markdown, $media);

    $failures = [];
    new ValidRichTextDocument()->validate('body', $parsed->toArray(), function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe([]);
})->with([
    'setext heading' => ["Title\n=====\n\nBody text."],
    'html block' => ["<div>\nraw html\n</div>\n\nAfter."],
    'deep heading with marks' => ['###### **deep** heading'],
    'mixed image and text' => ['before ![pic](media:7) after'],
    'loose list' => ["- a\n\n- b\n\n- c"],
    'indented code' => ["    indented code line\n\nAfter."],
    'autolink' => ['<https://example.com>'],
    'reference link' => ["[ref][1]\n\n[1]: https://example.com"],
]);

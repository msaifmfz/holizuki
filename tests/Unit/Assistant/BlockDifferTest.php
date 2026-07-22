<?php

use App\Domain\Assistant\Services\BlockDiffer;

test('identical drafts produce no hunks', function (): void {
    $markdown = "## Title\n\nParagraph one.\n\nParagraph two.\n";

    expect(new BlockDiffer()->diff($markdown, $markdown))->toBe([]);
});

test('a rewritten paragraph becomes one hunk with anchors', function (): void {
    $old = "## Title\n\nOld paragraph.\n\nClosing thoughts.\n";
    $new = "## Title\n\nMuch better paragraph.\n\nClosing thoughts.\n";

    $hunks = new BlockDiffer()->diff($old, $new);

    expect($hunks)->toHaveCount(1)
        ->and($hunks[0]['old_blocks'])->toBe('Old paragraph.')
        ->and($hunks[0]['new_blocks'])->toBe('Much better paragraph.')
        ->and($hunks[0]['anchor_before'])->toBe('## Title')
        ->and($hunks[0]['anchor_after'])->toBe('Closing thoughts.');
});

test('a pure insertion has empty old blocks', function (): void {
    $old = "First.\n\nLast.\n";
    $new = "First.\n\nInserted.\n\nLast.\n";

    $hunks = new BlockDiffer()->diff($old, $new);

    expect($hunks)->toHaveCount(1)
        ->and($hunks[0]['old_blocks'])->toBe('')
        ->and($hunks[0]['new_blocks'])->toBe('Inserted.')
        ->and($hunks[0]['anchor_before'])->toBe('First.')
        ->and($hunks[0]['anchor_after'])->toBe('Last.');
});

test('a deletion has empty new blocks', function (): void {
    $old = "First.\n\nDoomed.\n\nLast.\n";
    $new = "First.\n\nLast.\n";

    $hunks = new BlockDiffer()->diff($old, $new);

    expect($hunks)->toHaveCount(1)
        ->and($hunks[0]['old_blocks'])->toBe('Doomed.')
        ->and($hunks[0]['new_blocks'])->toBe('');
});

test('distant edits become separate hunks', function (): void {
    $old = "One.\n\nTwo.\n\nThree.\n\nFour.\n\nFive.\n";
    $new = "One!\n\nTwo.\n\nThree.\n\nFour.\n\nFive!\n";

    $hunks = new BlockDiffer()->diff($old, $new);

    expect($hunks)->toHaveCount(2)
        ->and($hunks[0]['old_blocks'])->toBe('One.')
        ->and($hunks[0]['new_blocks'])->toBe('One!')
        ->and($hunks[0]['anchor_before'])->toBeNull()
        ->and($hunks[1]['old_blocks'])->toBe('Five.')
        ->and($hunks[1]['anchor_before'])->toBe('Four.')
        ->and($hunks[1]['anchor_after'])->toBeNull();
});

test('blank lines inside code fences do not split blocks', function (): void {
    $markdown = "Intro.\n\n```php\nline one;\n\nline two;\n```\n\nOutro.\n";

    $blocks = new BlockDiffer()->splitBlocks($markdown);

    expect($blocks)->toBe([
        'Intro.',
        "```php\nline one;\n\nline two;\n```",
        'Outro.',
    ]);
});

test('appending to an empty draft yields a single insertion hunk', function (): void {
    $hunks = new BlockDiffer()->diff('', "New content.\n");

    expect($hunks)->toHaveCount(1)
        ->and($hunks[0]['old_blocks'])->toBe('')
        ->and($hunks[0]['new_blocks'])->toBe('New content.')
        ->and($hunks[0]['anchor_before'])->toBeNull()
        ->and($hunks[0]['anchor_after'])->toBeNull();
});

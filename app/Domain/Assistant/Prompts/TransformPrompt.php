<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Prompts;

/**
 * One-shot prompt for a selection transform: the author highlighted a
 * passage and picked an operation; the agent edits exactly that passage in
 * draft.md and the diff comes back as a reviewable body change.
 */
class TransformPrompt
{
    /** @var list<string> */
    public const array PRESETS = ['improve', 'expand', 'shorten', 'simplify', 'custom'];

    public function build(string $selection, string $preset, ?string $instruction): string
    {
        $task = match ($preset) {
            'improve' => 'Rewrite it to be clearer and more engaging while keeping its meaning and roughly its length.',
            'expand' => 'Expand it with more depth, detail, or a concrete example — roughly double the length.',
            'shorten' => 'Tighten it to roughly half the length without losing the key point.',
            'simplify' => 'Rewrite it in plainer language that a general reader can follow.',
            default => (string) $instruction,
        };

        return <<<PROMPT
        The author selected this passage in draft.md:

        <selection>
        {$selection}
        </selection>

        {$task}

        Find the selected passage in draft.md (its markdown formatting may differ slightly from the plain text above) and edit only that passage. Leave the rest of the file untouched and do not change meta.json.
        PROMPT;
    }
}

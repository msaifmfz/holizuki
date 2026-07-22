<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Prompts;

/**
 * The idea → outline → draft flow. `start` turns a topic into a working
 * outline (asking clarifying questions first when the topic is thin);
 * refinement happens through ordinary chat on the same session; `draft`
 * writes the article from the approved outline.
 */
class OutlinePrompt
{
    /** @var list<string> */
    public const array STEPS = ['start', 'draft'];

    public function start(string $topic): string
    {
        return <<<PROMPT
        The author wants to start a new article. Their topic and notes:

        <topic>
        {$topic}
        </topic>

        Check meta.json and images/manifest.json for extra context. Then:
        - If the topic leaves important decisions open (angle, audience, scope), ask at most three short clarifying questions in your reply and do not edit any files yet.
        - Otherwise write outline.md: a working title on the first line, then the planned sections as a markdown outline (## headings with a one-line note each). Show the outline in your reply and invite the author to adjust it — they can answer you right here in the chat.

        Do not write draft.md yet.
        PROMPT;
    }

    public function draft(?string $notes): string
    {
        $extra = $notes === null || trim($notes) === ''
            ? ''
            : "\n\nExtra instructions from the author:\n{$notes}";

        return <<<PROMPT
        The outline is approved — write the article now.

        Read outline.md and write the full draft into draft.md, section by section, following the outline's structure. Aim for a complete, publishable first draft in the author's voice (match any existing text). If images in images/manifest.json fit a section, place them with descriptive alt text. Also fill title and excerpt in meta.json based on what you wrote.{$extra}

        Keep your chat reply to a short summary of what you drafted.
        PROMPT;
    }
}

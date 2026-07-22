<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Prompts;

/**
 * One-shot prompt asking the agent to fill the requested metadata fields in
 * meta.json based on the current draft.
 */
class MetadataPrompt
{
    /** @var list<string> */
    public const array GENERATABLE_FIELDS = [
        'title', 'excerpt', 'seo_title', 'meta_description', 'og_title', 'og_description', 'tags',
    ];

    /** @param list<string> $fields */
    public function build(array $fields): string
    {
        $requested = implode(', ', $fields);

        return <<<PROMPT
        Read draft.md, then update these keys in meta.json based on the article's content: {$requested}.

        Guidelines:
        - excerpt: a compelling 1–2 sentence teaser, max 500 characters, plain text.
        - seo_title: max 60 characters, front-load the primary keyword.
        - meta_description: 120–155 characters, active voice, makes the reader want to click.
        - og_title / og_description: social-share variants — punchier than the SEO pair.
        - title: only rewrite it when asked; propose a clear, specific headline.
        - tags: 3–6 short lowercase topic tags; reuse existing tags when they fit.

        Only change the requested keys. Do not touch draft.md.
        PROMPT;
    }
}

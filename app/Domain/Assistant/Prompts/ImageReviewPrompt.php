<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Prompts;

/**
 * One-shot photo review: the agent looks at every image file (it can see
 * them), fixes weak alt text and captions, and suggests placements for
 * unused images. Everything comes back as reviewable changes.
 */
class ImageReviewPrompt
{
    public function build(): string
    {
        return <<<'PROMPT'
        Review this article's images. Read images/manifest.json, then Read each image file listed there — you can see the images themselves.

        1. Alt text and captions: for every image referenced in draft.md, make its alt text genuinely describe what the image shows (not the filename, not the article topic) and add a caption where one would help the reader. Update the image references in draft.md. If the manifest has a featured image, fill featured_image_alt (and featured_image_caption if useful) in meta.json the same way.
        2. Placement: for manifest images with "usedInDraft": false, insert the ones that would strengthen the article at the spot they fit best in draft.md, with proper alt text. Leave out images that add nothing — say so briefly in your reply instead.

        Change only image references and the two featured-image fields; leave the surrounding prose untouched.
        PROMPT;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AuthoringArtifactType: string
{
    case Plan = 'plan';
    case Draft = 'draft';
    case ResearchFinding = 'research_finding';
    case ImageGuidance = 'image_guidance';
    case ImageGroupSuggestion = 'image_group_suggestion';
    case ImagePassage = 'image_passage';
}

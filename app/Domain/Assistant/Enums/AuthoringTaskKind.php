<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AuthoringTaskKind: string
{
    case Plans = 'plans';
    case ImageGuidance = 'image_guidance';
    case ImagePassages = 'image_passages';
    case Draft = 'draft';
    case Research = 'research';

    public function assistantTaskType(): AssistantTaskType
    {
        return match ($this) {
            self::Plans => AssistantTaskType::Plans,
            self::ImageGuidance, self::ImagePassages => AssistantTaskType::Plans,
            self::Draft => AssistantTaskType::Draft,
            self::Research => AssistantTaskType::Research,
        };
    }
}

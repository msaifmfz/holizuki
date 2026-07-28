<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum AuthoringJourneyStep: string
{
    case Idea = 'idea';
    case Sources = 'sources';
    case Plans = 'plans';
    case Images = 'images';
    case Draft = 'draft';
    case Review = 'review';
}

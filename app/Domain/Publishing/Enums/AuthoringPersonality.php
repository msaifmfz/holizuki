<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum AuthoringPersonality: string
{
    case Friendly = 'friendly';
    case Professional = 'professional';
    case Creative = 'creative';
    case Custom = 'custom';
}

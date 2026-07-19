<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Exceptions;

use App\Domain\Publishing\Models\Post;
use DomainException;

class CannotFeatureUnpublishedPost extends DomainException
{
    public function __construct(public readonly Post $post)
    {
        parent::__construct('Only published posts can be featured.');
    }
}

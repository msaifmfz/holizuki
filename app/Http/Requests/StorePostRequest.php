<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Post;

class StorePostRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('create', Post::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}

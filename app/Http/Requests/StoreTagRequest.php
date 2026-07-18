<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Validation\Rule;

class StoreTagRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('create', Tag::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(Tag::class, 'name')],
        ];
    }
}

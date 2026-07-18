<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundCategory());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Category::class, 'name')->ignore($this->boundCategory())],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}

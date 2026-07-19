<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Taxonomy\Models\Category;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('create', Category::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Category::class, 'name')],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Taxonomy\Models\Tag;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundTag());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(Tag::class, 'name')->ignore($this->boundTag())],
        ];
    }
}

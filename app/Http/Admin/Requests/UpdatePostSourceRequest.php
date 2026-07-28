<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

final class UpdatePostSourceRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'approved' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::enum(PostSourceVisibility::class)],
        ];
    }
}

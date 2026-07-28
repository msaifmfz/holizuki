<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Http\AuthenticatedRequest;

final class StorePostSourceNoteRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
        ];
    }
}

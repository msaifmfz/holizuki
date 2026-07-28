<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Publishing\Rules\SafeExternalSourceUrl;
use App\Http\AuthenticatedRequest;

final class StorePostSourceUrlRequest extends AuthenticatedRequest
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
            'url' => ['bail', 'required', 'string', 'max:2048', 'url:http,https', new SafeExternalSourceUrl],
        ];
    }
}

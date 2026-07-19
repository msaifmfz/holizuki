<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Http\AuthenticatedRequest;

class DeletePostRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('delete', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}

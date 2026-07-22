<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Http\AuthenticatedRequest;

class ChangeDecisionRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}

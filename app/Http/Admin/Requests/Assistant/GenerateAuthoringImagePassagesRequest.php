<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Http\Admin\Requests\Assistant\Concerns\ResolvesAssistantRequestId;
use App\Http\AuthenticatedRequest;

final class GenerateAuthoringImagePassagesRequest extends AuthenticatedRequest
{
    use ResolvesAssistantRequestId;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->assistantRequestIdRules();
    }
}

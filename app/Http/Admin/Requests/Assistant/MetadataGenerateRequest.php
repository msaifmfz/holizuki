<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Assistant\Prompts\MetadataPrompt;
use App\Http\Admin\Requests\Assistant\Concerns\ResolvesAssistantRequestId;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class MetadataGenerateRequest extends AuthenticatedRequest
{
    use ResolvesAssistantRequestId;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1', 'max:'.count(MetadataPrompt::GENERATABLE_FIELDS)],
            'fields.*' => ['string', 'distinct', Rule::in(MetadataPrompt::GENERATABLE_FIELDS)],
            ...$this->assistantRequestIdRules(),
        ];
    }

    /** @return list<string> */
    public function fields(): array
    {
        $fields = $this->validated('fields');

        return is_array($fields) ? array_values(array_filter($fields, is_string(...))) : [];
    }
}

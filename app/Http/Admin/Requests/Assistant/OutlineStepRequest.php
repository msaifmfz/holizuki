<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Assistant\Prompts\OutlinePrompt;
use App\Http\Admin\Requests\Assistant\Concerns\ResolvesAssistantRequestId;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class OutlineStepRequest extends AuthenticatedRequest
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
            'step' => ['required', 'string', Rule::in(OutlinePrompt::STEPS)],
            'message' => ['nullable', 'string', 'max:10000', 'required_if:step,start'],
            ...$this->assistantRequestIdRules(),
        ];
    }

    public function step(): string
    {
        $step = $this->validated('step');

        return is_string($step) ? $step : 'start';
    }

    public function message(): ?string
    {
        $message = $this->validated('message');

        return is_string($message) && $message !== '' ? $message : null;
    }
}

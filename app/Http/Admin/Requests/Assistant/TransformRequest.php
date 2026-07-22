<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Assistant\Prompts\TransformPrompt;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class TransformRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'selection' => ['required', 'string', 'max:20000'],
            'preset' => ['required', 'string', Rule::in(TransformPrompt::PRESETS)],
            'instruction' => ['nullable', 'string', 'max:1000', 'required_if:preset,custom'],
        ];
    }

    public function selection(): string
    {
        $selection = $this->validated('selection');

        return is_string($selection) ? $selection : '';
    }

    public function preset(): string
    {
        $preset = $this->validated('preset');

        return is_string($preset) ? $preset : 'improve';
    }

    public function instruction(): ?string
    {
        $instruction = $this->validated('instruction');

        return is_string($instruction) && $instruction !== '' ? $instruction : null;
    }
}

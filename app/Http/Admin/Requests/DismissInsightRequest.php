<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DismissInsightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', Rule::in([
            'not_relevant', 'intentionally_designed', 'insufficient_context',
            'data_incorrect', 'snooze', 'already_completed',
        ])]];
    }
}

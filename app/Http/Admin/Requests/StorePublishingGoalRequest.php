<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublishingGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cadence' => ['required', Rule::in(['weekly', 'monthly'])],
            'target' => [
                'required',
                'integer',
                'min:1',
                Rule::when($this->input('cadence') === 'weekly', ['max:7'], ['max:31']),
            ],
        ];
    }
}

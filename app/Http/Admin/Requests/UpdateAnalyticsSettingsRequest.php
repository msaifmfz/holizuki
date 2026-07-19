<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnalyticsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'material_gap_points' => ['required', 'integer', 'between:5,50'],
            'show_exploratory_insights' => ['required', 'boolean'],
        ];
    }
}

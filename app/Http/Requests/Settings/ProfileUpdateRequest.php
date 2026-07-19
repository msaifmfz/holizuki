<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Http\Requests\AuthenticatedRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends AuthenticatedRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->authenticatedUser()->id;

        return [
            ...$this->profileRules($userId),
            'author_slug' => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash:ascii',
                Rule::unique(User::class, 'author_slug')->ignore($userId),
            ],
            'bio' => ['nullable', 'string', 'max:500'],
            'social_links' => ['nullable', 'array:website,x,github,linkedin'],
            'social_links.website' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.x' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.github' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.linkedin' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\AuthenticatedRequest;

class UpdateProfileAvatarRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }
}

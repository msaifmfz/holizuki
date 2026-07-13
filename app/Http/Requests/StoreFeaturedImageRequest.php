<?php

declare(strict_types=1);

namespace App\Http\Requests;

class StoreFeaturedImageRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'alt_text' => ['required', 'string', 'max:255'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

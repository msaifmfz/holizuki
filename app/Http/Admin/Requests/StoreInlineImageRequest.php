<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Http\AuthenticatedRequest;

class StoreInlineImageRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=1,min_height=1,max_width=10000,max_height=10000',
                'max:5120',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Publishing\Models\PostMedia;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Validator;

final class StorePostMediaRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.PostMedia::MAX_PER_POST],
            'images.*' => [
                'bail',
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=1,min_height=1,max_width=10000,max_height=10000',
                'max:5120',
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $storyPhotoCount = PostMedia::query()
                    ->whereBelongsTo($this->boundPost())
                    ->count();
                $uploadedPhotoCount = count($this->file('images', []));

                if ($storyPhotoCount + $uploadedPhotoCount > PostMedia::MAX_PER_POST) {
                    $validator->errors()->add('images', PostMedia::MAX_PER_POST_MESSAGE);
                }
            },
        ];
    }
}

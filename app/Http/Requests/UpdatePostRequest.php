<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PostValidationRules;
use App\Enums\PostStatus;
use Override;

class UpdatePostRequest extends AuthenticatedRequest
{
    use PostValidationRules;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $post = $this->boundPost();

        if ($post->status === PostStatus::Published || $post->isScheduled()) {
            return [
                ...$this->publishRules($post),
                'slug_is_manual' => ['required', 'boolean'],
                'force' => ['sometimes', 'boolean'],
            ];
        }

        return $this->draftRules($post);
    }

    /** @return array<string, mixed> */
    #[Override]
    public function validationData(): array
    {
        $data = [];

        foreach (parent::validationData() as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        $data['featured_image_path'] = $this->boundPost()->featured_image_path;

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PostValidationRules;
use Override;

class PublishPostRequest extends AuthenticatedRequest
{
    use PostValidationRules;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->publishRules($this->boundPost());
    }

    /** @return array<string, mixed> */
    #[Override]
    public function validationData(): array
    {
        $post = $this->boundPost();

        $data = [];

        foreach (parent::validationData() as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }
        $data['title'] = $post->title;
        $data['slug'] = $post->slug;
        $data['excerpt'] = $post->excerpt;
        $data['body'] = $post->body;
        $data['featured_image_path'] = $post->featured_image_path;
        $data['featured_image_alt'] = $post->featured_image_alt;

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Http\Admin\Concerns\PostValidationRules;
use App\Http\AuthenticatedRequest;
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
        $draft = $post->draft()->firstOrFail();

        $data = [];

        foreach (parent::validationData() as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }
        $data['title'] = $draft->title;
        $data['slug'] = $draft->slug;
        $data['excerpt'] = $draft->excerpt;
        $data['body'] = $draft->body?->toArray();
        $data['featured_image_path'] = $draft->featured_image_path;
        $data['featured_image_alt'] = $draft->featured_image_alt;
        $data['category_id'] = $draft->category_id;

        return $data;
    }
}

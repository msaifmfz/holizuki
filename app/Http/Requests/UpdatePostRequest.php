<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PostValidationRules;
use App\Enums\PostStatus;
use Illuminate\Support\Str;
use Override;

class UpdatePostRequest extends AuthenticatedRequest
{
    use PostValidationRules;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /**
     * Normalize the slug before the unique rule runs, so a slug that only
     * differs pre-normalization (e.g. "My-Post" vs "my-post") fails
     * validation instead of the database unique index. Slugs that normalize
     * to an empty string pass through untouched; the save action falls back
     * to a title-derived slug for those.
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug) && Str::slug($slug) !== '') {
            $this->merge(['slug' => Str::slug($slug)]);
        }
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

        if (! array_key_exists('category_id', $data)) {
            $data['category_id'] = $this->boundPost()->category_id;
        }

        return $data;
    }
}

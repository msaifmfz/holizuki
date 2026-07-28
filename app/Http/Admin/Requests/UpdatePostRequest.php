<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Http\Admin\Concerns\PostValidationRules;
use App\Http\AuthenticatedRequest;
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
        return $this->draftRules($this->boundPost());
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

        if (! array_key_exists('category_id', $data)) {
            $data['category_id'] = $this->boundPost()->draft()->value('category_id');
        }

        return $data;
    }
}

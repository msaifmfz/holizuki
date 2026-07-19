<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Rules\PostOwnsRichTextMedia;
use App\Rules\ValidRichTextDocument;
use Illuminate\Validation\Rule;

trait PostValidationRules
{
    /** @return array<string, mixed> */
    protected function draftRules(Post $post): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Post::class, 'slug')->ignore($post)],
            'slug_is_manual' => ['required', 'boolean'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'array', new ValidRichTextDocument, new PostOwnsRichTextMedia($post)],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'featured_image_caption' => ['nullable', 'string', 'max:500'],
            ...$this->seoRules(),
            'lock_version' => ['required', 'integer', 'min:0'],
            'force' => ['sometimes', 'boolean'],
            ...$this->taxonomyRules(requireCategory: false),
        ];
    }

    /** @return array<string, mixed> */
    protected function publishRules(Post $post): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Post::class, 'slug')->ignore($post)],
            'excerpt' => ['required', 'string', 'max:500'],
            'body' => ['required', 'array', new ValidRichTextDocument(requireContent: true), new PostOwnsRichTextMedia($post)],
            'featured_image_path' => ['required', 'string'],
            'featured_image_alt' => ['required', 'string', 'max:255'],
            'featured_image_caption' => ['nullable', 'string', 'max:500'],
            ...$this->seoRules(),
            'lock_version' => ['required', 'integer', 'min:0'],
            ...$this->taxonomyRules(requireCategory: true),
        ];
    }

    /** @return array<string, mixed> */
    private function seoRules(): array
    {
        return [
            'seo_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'noindex' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function taxonomyRules(bool $requireCategory): array
    {
        return [
            'category_id' => [
                $requireCategory ? 'required' : 'nullable',
                'integer',
                Rule::exists(Category::class, 'id'),
            ],
            'author_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}

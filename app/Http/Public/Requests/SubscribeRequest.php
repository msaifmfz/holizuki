<?php

declare(strict_types=1);

namespace App\Http\Public\Requests;

use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'source_post_id' => [
                'nullable',
                'integer',
                Rule::exists(Post::class, 'id')->where(
                    fn (Builder $query): Builder => $query->where('status', PostStatus::Published->value),
                ),
            ],
            'source_location' => ['required', Rule::in(['article_end', 'footer', 'registration'])],
            'consent_version' => ['required', Rule::in([config()->string('community.consent_version')])],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        return $comment instanceof Comment && $this->user()?->can('moderate', $comment) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CommentStatus::class)->only([
                CommentStatus::Approved,
                CommentStatus::Rejected,
                CommentStatus::Deleted,
            ])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

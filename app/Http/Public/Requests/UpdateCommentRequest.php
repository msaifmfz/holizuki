<?php

declare(strict_types=1);

namespace App\Http\Public\Requests;

use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('comment');
        $user = $this->user();

        return $comment instanceof Comment
            && $user instanceof User
            && $comment->isEditableBy($user);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.config()->integer('community.comment_max_length')],
        ];
    }
}

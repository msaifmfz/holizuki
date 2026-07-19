<?php

declare(strict_types=1);

namespace App\Http\Public\Requests;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->hasVerifiedEmail()
            && ($user->isReader() || $user->isAdministrator());
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.config()->integer('community.comment_max_length')],
        ];
    }
}

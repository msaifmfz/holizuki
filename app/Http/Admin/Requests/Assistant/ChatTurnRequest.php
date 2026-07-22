<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Http\AuthenticatedRequest;

class ChatTurnRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
        ];
    }

    public function message(): string
    {
        $message = $this->validated('message');

        return is_string($message) ? $message : '';
    }
}

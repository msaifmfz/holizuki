<?php

declare(strict_types=1);

namespace App\Http\Public\Requests;

use App\Domain\Identity\Concerns\PasswordValidationRules;
use App\Http\AuthenticatedRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class ReaderPasswordUpdateRequest extends AuthenticatedRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->isReader();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}

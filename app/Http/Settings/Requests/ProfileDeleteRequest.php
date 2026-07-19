<?php

declare(strict_types=1);

namespace App\Http\Settings\Requests;

use App\Domain\Identity\Concerns\PasswordValidationRules;
use App\Http\AuthenticatedRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class ProfileDeleteRequest extends AuthenticatedRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}

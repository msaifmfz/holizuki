<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Analytics\Models\AuthorGoalPeriod;
use Illuminate\Foundation\Http\FormRequest;

class PauseGoalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $period = $this->route('period');

        return $user?->isAdministrator() === true
            && $period instanceof AuthorGoalPeriod
            && $period->user_id === $user->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }
}

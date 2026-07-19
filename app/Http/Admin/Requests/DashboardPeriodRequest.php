<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Analytics\ValueObjects\DashboardPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DashboardPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::in(['7d', '28d', '90d', 'year', 'custom'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function period(): DashboardPeriod
    {
        $period = $this->string('period', '28d')->toString();
        $from = $this->string('from')->toString();
        $to = $this->string('to')->toString();

        try {
            return DashboardPeriod::fromInput(
                $period,
                $from === '' ? null : $from,
                $to === '' ? null : $to,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['period' => $exception->getMessage()]);
        }
    }
}

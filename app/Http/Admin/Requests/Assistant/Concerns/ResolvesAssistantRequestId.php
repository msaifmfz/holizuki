<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant\Concerns;

use Illuminate\Support\Str;

trait ResolvesAssistantRequestId
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('request_id')) {
            $this->merge(['request_id' => (string) Str::uuid()]);
        }
    }

    /** @return array<string, list<string>> */
    protected function assistantRequestIdRules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
        ];
    }

    public function requestId(): string
    {
        $requestId = $this->validated('request_id');

        return is_string($requestId) ? $requestId : '';
    }
}

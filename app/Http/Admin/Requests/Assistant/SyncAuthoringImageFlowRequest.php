<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

final class SyncAuthoringImageFlowRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'blocks' => ['required', 'array', 'list', 'max:20'],
            'blocks.*.id' => ['sometimes', 'nullable', 'uuid'],
            'blocks.*.usage' => ['required', Rule::enum(AuthoringImageCompositionUsage::class)],
            'blocks.*.section_heading' => ['present', 'nullable', 'string', 'max:255'],
            'blocks.*.media_ids' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'blocks.*.media_ids.*' => ['required', 'integer'],
        ];
    }
}

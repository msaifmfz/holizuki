<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Services\ValidPhotoPassageMarkdown;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Rule;

final class UpdateAuthoringImageCompositionRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'selected_passage_id' => [
                'present',
                'nullable',
                'uuid',
                Rule::exists(AuthoringArtifact::class, 'public_id'),
            ],
            'passage_markdown' => [
                'required',
                'string',
                'max:12000',
                new ValidPhotoPassageMarkdown,
            ],
        ];
    }
}

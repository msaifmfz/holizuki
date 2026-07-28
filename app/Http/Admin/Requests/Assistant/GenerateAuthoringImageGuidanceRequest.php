<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Models\PostMedia;
use App\Http\Admin\Requests\Assistant\Concerns\ResolvesAssistantRequestId;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Validator;

final class GenerateAuthoringImageGuidanceRequest extends AuthenticatedRequest
{
    use ResolvesAssistantRequestId;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->assistantRequestIdRules();
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasImages = PostMedia::query()
                    ->whereBelongsTo($this->boundPost())
                    ->where('status', PostMediaStatus::Ready)
                    ->where('role', '!=', PostMediaRole::Hero)
                    ->where('placement', '!=', PostMediaPlacement::Featured)
                    ->exists();

                if (! $hasImages) {
                    $validator->errors()->add(
                        'images',
                        'Upload at least one non-featured story photo before asking for guidance.',
                    );
                }
            },
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Http\Admin\Requests\Assistant\Concerns\ResolvesAssistantRequestId;
use App\Http\AuthenticatedRequest;
use Illuminate\Validation\Validator;

final class GenerateAuthoringResearchRequest extends AuthenticatedRequest
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
                $journey = PostAuthoringJourney::query()->whereBelongsTo($this->boundPost())->first();

                if (! $journey instanceof PostAuthoringJourney || ! $journey->web_research_enabled) {
                    $validator->errors()->add(
                        'web_research_enabled',
                        'Enable web research for this post before starting research.',
                    );
                }
            },
        ];
    }
}

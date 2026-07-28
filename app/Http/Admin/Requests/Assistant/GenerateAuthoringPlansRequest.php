<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests\Assistant;

use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Http\Admin\Requests\Assistant\Concerns\ResolvesAssistantRequestId;
use App\Http\AuthenticatedRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

final class GenerateAuthoringPlansRequest extends AuthenticatedRequest
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
                $topic = $journey instanceof PostAuthoringJourney
                    ? Arr::get($journey->brief, 'topic')
                    : null;

                if (! is_string($topic) || trim($topic) === '') {
                    $validator->errors()->add(
                        'brief.topic',
                        'Describe what you want to write about before generating plans.',
                    );
                }
            },
        ];
    }
}

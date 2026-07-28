<?php

declare(strict_types=1);

namespace App\Http\Admin\Requests;

use App\Domain\Publishing\Rules\ValidPostSourceDocument;
use App\Http\AuthenticatedRequest;

final class StorePostSourceDocumentsRequest extends AuthenticatedRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->boundPost());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => [
                'bail',
                'required',
                'file',
                'max:10240',
                'extensions:pdf,txt,md',
                'mimetypes:application/pdf,text/plain,text/markdown',
                new ValidPostSourceDocument,
            ],
        ];
    }
}

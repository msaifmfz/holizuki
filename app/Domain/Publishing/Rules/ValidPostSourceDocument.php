<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class ValidPostSourceDocument implements ValidationRule
{
    /** @var list<string> */
    private const array EXTENSIONS = ['pdf', 'txt', 'md'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The source must be a valid PDF, TXT, or Markdown document.');

            return;
        }

        $extension = Str::lower($value->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONS, true)
            || ! $this->hasValidContents($value, $extension)) {
            $fail('The source must be a valid PDF, TXT, or Markdown document.');
        }
    }

    private function hasValidContents(UploadedFile $file, string $extension): bool
    {
        return match ($extension) {
            'pdf' => $this->isPdf($file),
            'txt', 'md' => $this->isText($file),
            default => false,
        };
    }

    private function isPdf(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 5);
        fclose($handle);

        return $signature === '%PDF-';
    }

    /**
     * Uploads run to several megabytes, so the file is walked in chunks rather
     * than held in memory. Chunks are cut on a UTF-8 character boundary before
     * validating so a multi-byte character spanning two reads is not misread as
     * invalid encoding.
     */
    private function isText(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        $carry = '';

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 64 * 1024);

                if ($chunk === false) {
                    return false;
                }

                $buffer = $carry.$chunk;

                if (str_contains($buffer, "\0")) {
                    return false;
                }

                $complete = mb_strcut($buffer, 0, strlen($buffer), 'UTF-8');
                $carry = substr($buffer, strlen($complete));

                if (! mb_check_encoding($complete, 'UTF-8')) {
                    return false;
                }
            }
        } finally {
            fclose($handle);
        }

        return $carry === '';
    }
}

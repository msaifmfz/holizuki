<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Rules;

use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Translation\PotentiallyTranslatedString;

final readonly class PostOwnsRichTextSources implements ValidationRule
{
    public function __construct(
        private Post $post,
        private bool $requireApproved = false,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || ! is_array($value)) {
            return;
        }

        $sourceIds = RichTextDocument::fromArray($value)->referencedSourceIds();

        if ($sourceIds === []) {
            return;
        }

        $ownedSourceCount = PostSource::query()
            ->whereBelongsTo($this->post)
            ->whereKey($sourceIds)
            ->when(
                $this->requireApproved,
                fn (Builder $query): Builder => $query
                    ->whereNotNull('approved_at')
                    ->where('visibility', PostSourceVisibility::Public),
            )
            ->count();

        if ($ownedSourceCount !== count($sourceIds)) {
            $fail($this->requireApproved
                ? 'Every citation must reference an approved public source that belongs to this post.'
                : 'Every citation must reference a source that belongs to this post.');
        }
    }
}

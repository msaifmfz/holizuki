<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Post;
use App\Models\PostMedia;
use App\Support\RichTextDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PostOwnsRichTextMedia implements ValidationRule
{
    public function __construct(private readonly Post $post) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || ! is_array($value)) {
            return;
        }

        $mediaIds = RichTextDocument::referencedMediaIds($value);

        if ($mediaIds === []) {
            return;
        }

        $ownedMediaCount = PostMedia::query()
            ->whereBelongsTo($this->post)
            ->whereKey($mediaIds)
            ->count();

        if ($ownedMediaCount !== count($mediaIds)) {
            $fail('The post body contains an image that does not belong to this post.');
        }
    }
}

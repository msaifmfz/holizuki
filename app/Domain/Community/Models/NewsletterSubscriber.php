<?php

declare(strict_types=1);

namespace App\Domain\Community\Models;

use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Policies\NewsletterSubscriberPolicy;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string|null $email
 * @property string $email_hash
 * @property SubscriberStatus $status
 * @property string|null $confirmation_token_hash
 * @property string|null $unsubscribe_token_hash
 * @property int|null $source_post_id
 * @property string $source_method
 * @property string $source_location
 * @property string|null $source_content_key
 * @property string $consent_version
 * @property CarbonInterface|null $confirmation_sent_at
 * @property CarbonInterface|null $confirmation_expires_at
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $unsubscribed_at
 * @property CarbonInterface|null $erased_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'email', 'email_hash', 'source_post_id', 'source_method', 'source_location',
    'source_content_key', 'consent_version',
])]
#[Hidden(['email', 'email_hash', 'confirmation_token_hash', 'unsubscribe_token_hash'])]
#[UsePolicy(NewsletterSubscriberPolicy::class)]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => SubscriberStatus::Pending->value,
        'source_method' => 'form',
        'source_location' => 'footer',
    ];

    protected static function newFactory(): NewsletterSubscriberFactory
    {
        return NewsletterSubscriberFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'source_post_id');
    }

    public function isConfirmationExpired(): bool
    {
        return $this->confirmation_expires_at === null || $this->confirmation_expires_at->isPast();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'status' => SubscriberStatus::class,
            'confirmation_sent_at' => 'datetime',
            'confirmation_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }
}

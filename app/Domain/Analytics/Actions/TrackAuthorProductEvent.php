<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\AuthorProductEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TrackAuthorProductEvent
{
    /** @var list<string> */
    private const array ALLOWED_EVENTS = [
        'dashboard_open', 'period_change', 'goal_set', 'insight_action',
        'editor_open', 'editor_save', 'new_post_start',
    ];

    /**
     * @param  array<string, bool|int|float|string|null>  $metadata
     */
    public function handle(
        User $user,
        string $eventId,
        ?string $contextKey = null,
        array $metadata = [],
        ?string $deduplicationKey = null,
    ): AuthorProductEvent {
        if (! in_array($eventId, self::ALLOWED_EVENTS, true)) {
            throw new InvalidArgumentException('The author product event is not allowlisted.');
        }

        $occurredAt = now();
        $sessionKey = $this->sessionKey();
        $deduplicationKey ??= $eventId === 'dashboard_open'
            ? implode(':', [$eventId, $user->id, $sessionKey, $occurredAt->toDateString()])
            : implode(':', [$eventId, $user->id, $contextKey ?? 'none', (string) Str::uuid()]);

        return AuthorProductEvent::query()->firstOrCreate(
            ['deduplication_key' => Str::limit($deduplicationKey, 128, '')],
            [
                'user_id' => $user->id,
                'event_id' => $eventId,
                'context_key' => $contextKey === null ? null : Str::limit($contextKey, 96, ''),
                'metadata' => $metadata === [] ? null : $metadata,
                'occurred_at' => $occurredAt,
                'expires_at' => $occurredAt->copy()->addMonths(24),
            ],
        );
    }

    private function sessionKey(): string
    {
        $request = request();
        if (! $request->hasSession()) {
            return 'no-session';
        }

        $sessionKey = $request->session()->get('analytics.product-session-id');
        if (is_string($sessionKey) && $sessionKey !== '') {
            return $sessionKey;
        }

        $sessionKey = (string) Str::uuid();
        $request->session()->put('analytics.product-session-id', $sessionKey);

        return $sessionKey;
    }
}

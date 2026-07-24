<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Exceptions\AssistantUnavailableException;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AssistantAvailability
{
    public function __construct(
        private readonly ClaudeProcessEnvironment $environment,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     status: string,
     *     code: string|null,
     *     message: string|null,
     *     retry_at: string|null
     * }
     */
    public function state(): array
    {
        if (! config()->boolean('assistant.enabled', true)) {
            return $this->unavailableState(
                status: 'disabled',
                code: 'assistant_disabled',
                message: 'The assistant is disabled.',
            );
        }

        if ($this->environment->oauthToken() === null) {
            return $this->unavailableState(
                status: 'unavailable',
                code: AgentFailureCode::AuthenticationFailed->value,
                message: AgentFailureCode::AuthenticationFailed->message(),
            );
        }

        $state = Cache::get($this->cacheKey());

        if (! is_array($state)) {
            return $this->availableState();
        }

        if (($state['runtime_fingerprint'] ?? null) !== $this->runtimeFingerprint()) {
            $this->clear();

            return $this->availableState();
        }

        $status = is_string($state['status'] ?? null) ? $state['status'] : 'unavailable';
        $code = is_string($state['code'] ?? null) ? $state['code'] : AgentFailureCode::Unknown->value;
        $message = is_string($state['message'] ?? null)
            ? $state['message']
            : AgentFailureCode::Unknown->message();
        $retryAt = is_string($state['retry_at'] ?? null) ? $state['retry_at'] : null;

        if ($status === 'cooldown' && ($retryAt === null || now()->greaterThanOrEqualTo($retryAt))) {
            $this->clear();

            return $this->availableState();
        }

        return $this->unavailableState($status, $code, $message, $retryAt);
    }

    public function ensureCanStart(): void
    {
        $state = $this->state();

        if (! $state['available']) {
            throw AssistantUnavailableException::fromState($state);
        }
    }

    public function record(AgentEvent $event): void
    {
        if ($event->type === AgentEvent::TYPE_RATE_LIMIT && $event->isError) {
            $this->coolDownFromEvent($event);

            return;
        }

        if (! $event->isError || $event->code === null) {
            return;
        }

        $failureCode = AgentFailureCode::tryFrom($event->code);

        if ($failureCode?->blocksAvailability() === true) {
            $this->markUnavailable($failureCode->value, $failureCode->message());

            return;
        }

        if ($failureCode === AgentFailureCode::RateLimit) {
            $this->coolDownFromEvent($event);

            return;
        }

        if ($failureCode?->isTransientProviderFailure() === true) {
            $this->coolDownUntil(
                now()->addSeconds(config()->integer('assistant.availability.transient_cooldown_seconds', 60)),
                $failureCode->value,
                $failureCode->message(),
            );

            return;
        }

        if (in_array($failureCode, [
            AgentFailureCode::ProcessStart,
            AgentFailureCode::ProcessFailed,
            AgentFailureCode::StartupTimeout,
            AgentFailureCode::IdleTimeout,
            AgentFailureCode::ProtocolError,
        ], true)) {
            $this->coolDownUntil(
                now()->addSeconds(config()->integer('assistant.availability.transient_cooldown_seconds', 60)),
                $failureCode->value,
                $failureCode->message(),
            );
        }
    }

    public function markUnavailable(string $code, string $message): void
    {
        Cache::forever($this->cacheKey(), [
            'status' => 'unavailable',
            'code' => $code,
            'message' => $message,
            'retry_at' => null,
            'runtime_fingerprint' => $this->runtimeFingerprint(),
        ]);
    }

    public function coolDownUntil(DateTimeInterface $retryAt, string $code, string $message): void
    {
        $retryAtCarbon = now()->instance($retryAt)->utc();

        if ($retryAtCarbon->isPast()) {
            $retryAtCarbon = now()->addSecond();
        }

        $existingState = Cache::get($this->cacheKey());

        if (
            is_array($existingState)
            && ($existingState['runtime_fingerprint'] ?? null) === $this->runtimeFingerprint()
            && ($existingState['status'] ?? null) === 'cooldown'
            && is_string($existingState['retry_at'] ?? null)
        ) {
            try {
                $existingRetryAt = CarbonImmutable::parse($existingState['retry_at']);

                if (
                    $existingRetryAt->isFuture()
                    && $existingRetryAt->greaterThanOrEqualTo($retryAtCarbon)
                ) {
                    return;
                }
            } catch (Throwable) {
                // Replace malformed cache state with the validated cooldown below.
            }
        }

        Cache::put($this->cacheKey(), [
            'status' => 'cooldown',
            'code' => $code,
            'message' => $message,
            'retry_at' => $retryAtCarbon->toIso8601String(),
            'runtime_fingerprint' => $this->runtimeFingerprint(),
        ], $retryAtCarbon);
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Clear only a latched permanent failure (auth, billing, model). A
     * self-expiring cooldown is left intact so verifying the runtime cannot
     * shorten a genuine provider rate-limit window.
     */
    public function clearBlockingFailure(): void
    {
        $state = Cache::get($this->cacheKey());

        if (is_array($state) && ($state['status'] ?? null) === 'unavailable') {
            $this->clear();
        }
    }

    private function coolDownFromEvent(AgentEvent $event): void
    {
        $this->coolDownUntil(
            $this->rateLimitRetryAt($event->retryAt),
            AgentFailureCode::RateLimit->value,
            AgentFailureCode::RateLimit->message(),
        );
    }

    private function rateLimitRetryAt(?string $retryAt): DateTimeInterface
    {
        if ($retryAt !== null && trim($retryAt) !== '') {
            try {
                return CarbonImmutable::parse($retryAt);
            } catch (Throwable) {
                return $this->fallbackRateLimitRetryAt();
            }
        }

        return $this->fallbackRateLimitRetryAt();
    }

    private function fallbackRateLimitRetryAt(): DateTimeInterface
    {
        return now()->addSeconds(
            config()->integer('assistant.availability.rate_limit_fallback_seconds', 900),
        );
    }

    /**
     * @return array{
     *     available: true,
     *     status: 'available',
     *     code: null,
     *     message: null,
     *     retry_at: null
     * }
     */
    private function availableState(): array
    {
        return [
            'available' => true,
            'status' => 'available',
            'code' => null,
            'message' => null,
            'retry_at' => null,
        ];
    }

    /**
     * @return array{
     *     available: false,
     *     status: string,
     *     code: string,
     *     message: string,
     *     retry_at: string|null
     * }
     */
    private function unavailableState(
        string $status,
        string $code,
        string $message,
        ?string $retryAt = null,
    ): array {
        return [
            'available' => false,
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'retry_at' => $retryAt,
        ];
    }

    private function cacheKey(): string
    {
        return config()->string('assistant.availability.cache_key', 'assistant:availability');
    }

    private function runtimeFingerprint(): string
    {
        return hash('sha256', serialize([
            'binary' => config()->string('assistant.binary', 'claude'),
            'version' => config()->string('assistant.claude_version', ''),
            'oauth' => hash('sha256', (string) $this->environment->oauthToken()),
            'models' => config('assistant.models', []),
        ]));
    }
}

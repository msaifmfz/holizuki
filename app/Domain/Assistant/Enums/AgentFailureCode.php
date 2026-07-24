<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AgentFailureCode: string
{
    case AuthenticationFailed = 'authentication_failed';
    case OAuthOrganizationNotAllowed = 'oauth_org_not_allowed';
    case BillingError = 'billing_error';
    case RateLimit = 'rate_limit';
    case ProviderOverloaded = 'provider_overloaded';
    case InvalidRequest = 'invalid_request';
    case ModelNotFound = 'model_not_found';
    case ServerError = 'server_error';
    case MaxOutputTokens = 'max_output_tokens';
    case MaxTurns = 'max_turns';
    case MaxBudget = 'max_budget';
    case StructuredOutput = 'structured_output';
    case Execution = 'execution_error';
    case SessionNotFound = 'session_not_found';
    case ProcessStart = 'process_start_failed';
    case ProcessFailed = 'process_failed';
    case StartupTimeout = 'startup_timeout';
    case IdleTimeout = 'idle_timeout';
    case TurnTimeout = 'turn_timeout';
    case OutputLimit = 'output_limit';
    case ProtocolError = 'protocol_error';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public function message(): string
    {
        return match ($this) {
            self::AuthenticationFailed => 'The AI is not signed in on the server. Configure Claude Code OAuth and run assistant:doctor.',
            self::OAuthOrganizationNotAllowed => 'This Claude account cannot use the configured OAuth organization.',
            self::BillingError => 'The Claude subscription cannot run this request. Check its billing and usage status.',
            self::RateLimit => 'The Claude subscription usage limit has been reached. Try again after the cooldown.',
            self::ProviderOverloaded, self::ServerError => 'Claude is temporarily unavailable. Try again shortly.',
            self::InvalidRequest => 'Claude rejected this assistant request.',
            self::ModelNotFound => 'The configured Claude model is not available to this account.',
            self::MaxOutputTokens => 'The assistant reached its response limit before finishing.',
            self::MaxTurns => 'The assistant reached its turn limit before finishing.',
            self::MaxBudget => 'The assistant reached its configured usage budget before finishing.',
            self::StructuredOutput => 'The assistant could not produce a valid structured result.',
            self::SessionNotFound => 'The saved Claude conversation could not be found. The next turn will start fresh.',
            self::ProcessStart => 'The Claude Code process could not be started.',
            self::StartupTimeout => 'Claude Code did not start responding in time.',
            self::IdleTimeout => 'Claude Code stopped responding and was terminated.',
            self::TurnTimeout => 'The assistant took too long and was stopped.',
            self::OutputLimit => 'The assistant produced more output than the server can safely process.',
            self::Cancelled => 'The assistant turn was cancelled.',
            self::Execution, self::ProcessFailed, self::ProtocolError, self::Unknown => 'The assistant could not finish this request.',
        };
    }

    public function blocksAvailability(): bool
    {
        return in_array($this, [
            self::AuthenticationFailed,
            self::OAuthOrganizationNotAllowed,
            self::BillingError,
            self::ModelNotFound,
        ], true);
    }

    public function isTransientProviderFailure(): bool
    {
        return in_array($this, [
            self::ProviderOverloaded,
            self::ServerError,
        ], true);
    }

    public function invalidatesConversation(): bool
    {
        return in_array($this, [
            self::ProcessStart,
            self::ProcessFailed,
            self::StartupTimeout,
            self::IdleTimeout,
            self::TurnTimeout,
            self::OutputLimit,
            self::ProtocolError,
            self::Cancelled,
            self::Execution,
            self::Unknown,
        ], true);
    }
}

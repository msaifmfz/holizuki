<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AssistantTaskType: string
{
    case Chat = 'chat';
    case Transform = 'transform';
    case Metadata = 'metadata';
    case Outline = 'outline';
    case Images = 'images';

    public function model(): string
    {
        return config()->string('assistant.models.'.$this->value, 'claude-opus-4-8');
    }

    public function maxTurns(): int
    {
        return config()->integer('assistant.max_turns.'.$this->value, 10);
    }

    /**
     * Chat-style tasks resume the post's persistent Claude Code session so
     * the conversation keeps its memory; one-shot tasks start fresh.
     */
    public function usesPersistentSession(): bool
    {
        return match ($this) {
            self::Chat, self::Outline => true,
            self::Transform, self::Metadata, self::Images => false,
        };
    }
}

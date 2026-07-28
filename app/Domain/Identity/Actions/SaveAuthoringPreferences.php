<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

final class SaveAuthoringPreferences
{
    /**
     * @param  array{personality?: string, custom_instructions?: string|null}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        $updates = [];

        if (array_key_exists('personality', $attributes)) {
            $updates['authoring_personality'] = $attributes['personality'];
        }

        if (array_key_exists('custom_instructions', $attributes)) {
            $updates['authoring_custom_instructions'] = $attributes['custom_instructions'];
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return $user->refresh();
    }
}

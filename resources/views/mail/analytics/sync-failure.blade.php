<x-mail::message>
# Analytics synchronization needs attention

Google Analytics synchronization has failed at least three consecutive times. Existing dashboard values were preserved.

{{ $sanitizedError }}

Run `php artisan analytics:health` and review the masked analytics settings page before retrying.
</x-mail::message>

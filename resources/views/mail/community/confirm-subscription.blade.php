<x-mail::message>
# Confirm your subscription

Please confirm that you would like to receive new writing by email. This link expires in 48 hours.

<x-mail::button :url="$confirmationUrl">
Confirm subscription
</x-mail::button>

If you did not request this, you can ignore this message or [unsubscribe immediately]({{ $unsubscribeUrl }}).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

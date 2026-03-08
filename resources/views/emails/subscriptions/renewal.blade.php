<x-mail::message>
# Subscription Renewal Update

Hi {{ $user->name ?? 'there' }},

@if($status === 'attempting')
We are currently attempting to automatically renew your **{{ $plan->name }}** subscription.
@elseif($status === 'success')
Good news! Your **{{ $plan->name }}** subscription has been successfully renewed. Your new expiration date is {{ \Carbon\Carbon::parse($user->plans()->where('plan_id', $plan->id)->wherePivot('status', 'active')->first()->pivot->expires_at)->format('F j, Y') }}.
@elseif($status === 'failed')
Unfortunately, we were unable to renew your **{{ $plan->name }}** subscription.
@if($errorMessage)
**Reason:** {{ $errorMessage }}
@endif

Please ensure you have sufficient funds in your wallet to continue enjoying our services.
@endif

<x-mail::button :url="config('app.url') . '/plans'">
View My Plans
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

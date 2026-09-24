@extends('emails.layout')

@section('title', 'Contribution Renewal Update')

@section('content')
    <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
    <h1>Contribution renewal</h1>

    @if ($status === 'attempting')
        <p>We are currently attempting to automatically renew your <strong>{{ $plan->name }}</strong> contribution.</p>
    @elseif ($status === 'success')
        <p>Good news! Your <strong>{{ $plan->name }}</strong> contribution has been successfully renewed. Your new expiration date is {{ \Carbon\Carbon::parse($user->plans()->where('plan_id', $plan->id)->wherePivot('status', 'active')->first()->pivot->expires_at)->format('F j, Y') }}.</p>
    @elseif ($status === 'failed')
        <p>Unfortunately, we were unable to renew your <strong>{{ $plan->name }}</strong> contribution.</p>
        @if ($errorMessage)
            <div class="details-box">
                <div class="detail-row">
                    <span class="label">Reason</span>
                    <span class="value">{{ $errorMessage }}</span>
                </div>
            </div>
        @endif
        <p>Please ensure you have sufficient funds in your wallet to continue enjoying our services.</p>
    @endif

    <a href="{{ config('app.frontend_url', 'https://app.fajiri.org') }}/plans" class="btn">View my plans</a>
@endsection

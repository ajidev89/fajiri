@extends('emails.layout')

@section('title', 'Contribution Successful')

@section('content')
    <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
    <h1>Welcome to {{ $plan->name }}</h1>
    <p>Congratulations! You've successfully subscribed to the <strong>{{ $plan->name }}</strong> plan. Your account has been upgraded, and you now have access to premium features.</p>

    <div class="details-box">
        <div class="detail-row">
            <span class="label">Plan</span>
            <span class="value">{{ $plan->name }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Amount paid</span>
            <span class="value">{{ $currency }} {{ number_format($amount, 2) }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Status</span>
            <span class="value">Active</span>
        </div>
    </div>

    <p>If you have any questions, feel free to reach out to our support team.</p>

    <a href="{{ config('app.frontend_url', 'https://app.fajiri.org') }}/dashboard" class="btn">Go to dashboard</a>
@endsection

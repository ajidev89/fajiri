@extends('emails.layout')

@section('title', 'Deposit Successful')

@section('content')
    <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
    <h1>Deposit successful</h1>
    <p>Your wallet has been successfully funded. The balance is now available for your use on the platform.</p>

    <div class="details-box">
        <div class="detail-row">
            <span class="label">Amount</span>
            <span class="value">{{ $currency }} {{ number_format($amount, 2) }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Reference</span>
            <span class="value">{{ $reference }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Date</span>
            <span class="value">{{ now()->format('M d, Y H:i') }}</span>
        </div>
    </div>

    <p>Thank you for choosing Fajiri.</p>

    <a href="{{ config('app.frontend_url', 'https://app.fajiri.org') }}/wallet" class="btn">View wallet balance</a>
@endsection

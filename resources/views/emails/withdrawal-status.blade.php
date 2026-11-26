@extends('emails.layout')

@section('title', $heading)

@section('content')
    <p>Hi {{ $forAdmin ? 'team' : ($withdrawal->user->profile->first_name ?? 'there') }},</p>
    <h1>{{ $heading }}</h1>
    <p>{{ $body }}</p>

    <div class="details-box">
        <div class="detail-row">
            <span class="label">Amount</span>
            <span class="value">{{ $withdrawal->currency }} {{ number_format((float) $withdrawal->amount, 2) }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Reference</span>
            <span class="value">{{ $withdrawal->reference }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Status</span>
            <span class="value">{{ $withdrawal->status->label() }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Bank</span>
            <span class="value">{{ $withdrawal->account_snapshot['bank_name'] ?? '-' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Account</span>
            <span class="value">
                {{ $withdrawal->account_snapshot['account_name'] ?? '' }}
                •••• {{ substr((string) ($withdrawal->account_snapshot['account_number'] ?? $withdrawal->account_snapshot['iban'] ?? ''), -4) }}
            </span>
        </div>
        @if ($withdrawal->payout_reference)
            <div class="detail-row">
                <span class="label">Payment reference</span>
                <span class="value">{{ $withdrawal->payout_reference }}</span>
            </div>
        @endif
    </div>

    @unless ($forAdmin)
        <a href="{{ config('app.frontend_url') }}/profile" class="btn">View my wallet</a>
    @endunless
@endsection

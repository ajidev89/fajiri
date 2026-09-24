@extends('emails.layout')

@section('title', 'Verification Code')

@section('styles')
.content {
    text-align: center;
}
.otp-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 32px;
    margin: 8px 0 24px;
    display: inline-block;
    min-width: 240px;
}
.otp-code {
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 42px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: 8px;
    margin: 0;
}
.muted {
    font-size: 14px;
    color: #94a3b8;
}
@endsection

@section('content')
    <h1>Verify your identity</h1>
    <p>Please use the verification code below to complete your action. This code is valid for 5 minutes.</p>

    <div class="otp-container">
        <p class="otp-code">{{ $code }}</p>
    </div>

    <p class="muted">This code will expire at {{ now()->addMinutes(5)->format('H:i') }} UTC.</p>
    <p class="muted">If you did not request this code, please ignore this email or contact support if you have concerns.</p>
@endsection

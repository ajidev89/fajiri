@extends('emails.layout')

@section('title', 'Verification Update')

@section('content')
    <p>Hi {{ $user?->profile?->first_name ?? 'there' }},</p>
    <h1>Verification update</h1>

    @if ($status === 'approved')
        <p>Your identity verification has been approved.</p>
    @elseif ($status === 'resubmission')
        <p>Your identity verification needs to be submitted again. Please review your details and try once more.</p>
    @elseif ($status === 'declined')
        <p>Your identity verification was declined. Contact support if you believe this is a mistake.</p>
    @else
        <p>Your identity verification status is now {{ str_replace('_', ' ', $status) }}.</p>
    @endif
@endsection

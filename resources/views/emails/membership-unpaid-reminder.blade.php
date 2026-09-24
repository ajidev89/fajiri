@extends('emails.layout')

@section('title', 'Subscribe to your membership')

@section('content')
    <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
    <h1>Subscribe to a membership</h1>
    <p>You have not paid for a membership yet. Subscribe to a plan to get started and keep your place in the community.</p>

    <a href="{{ config('app.frontend_url', 'https://app.fajiri.org') }}/plans" class="btn">Subscribe</a>
@endsection

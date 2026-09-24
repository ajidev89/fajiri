@extends('emails.layout')

@section('title', 'New Web Form Submission')

@section('content')
    <h1>New message</h1>
    <p>You have received a new message from the Get in Touch form.</p>

    <div class="details-box">
        <div class="detail-row">
            <span class="label">From</span>
            <span class="value">{{ $data['first_name'] }} {{ $data['last_name'] }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Email</span>
            <span class="value">{{ $data['email'] }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Subject</span>
            <span class="value">{{ $data['subject'] }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Message</span>
            <span class="value">{{ $data['message'] }}</span>
        </div>
    </div>

    <p>You can reply directly to this email to respond to {{ $data['first_name'] }}.</p>
@endsection

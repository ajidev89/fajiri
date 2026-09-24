@extends('emails.layout')

@section('title', $announcement->title)

@section('styles')
.announcement-image {
    width: 100%;
    border-radius: 8px;
    margin-bottom: 24px;
}
@endsection

@section('content')
    <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
    <h1>{{ $announcement->title }}</h1>
    @if ($announcement->image_url)
        <img src="{{ $announcement->image_url }}" alt="" class="announcement-image">
    @endif
    <p>{!! nl2br(e($announcement->content)) !!}</p>
@endsection

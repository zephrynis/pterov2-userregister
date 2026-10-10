@extends('ext-userregister::layout')
@section('title', $title)
@section('body')
    <p class="ur-lead">{{ $message }}</p>
    @if ($title !== 'Your account is ready' && $title !== 'Registration is closed')
        <p class="ur-note"><a href="/extensions/userregister">Return to registration</a></p>
    @endif
@endsection

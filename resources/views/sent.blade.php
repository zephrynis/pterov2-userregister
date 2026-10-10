@extends('ext-userregister::layout')
@section('title', 'Check your inbox')
@section('body')
    <p class="ur-lead">If this address can be registered, a verification email will arrive shortly. Follow its link to finish creating your account.</p>
    @if ($email)
        <p class="ur-note">Email address: <span class="ur-email">{{ $email }}</span></p>
        <form method="post" action="/extensions/userregister/request">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button class="ur-button" type="submit">Resend verification email</button>
        </form>
    @endif
    <p class="ur-note">Check your spam folder, too. Allow at least {{ $cooldown }} seconds before resending. Sending is limited to prevent abuse; a new email replaces the previous link.</p>
    <p class="ur-note"><a href="/extensions/userregister">Use a different email address</a></p>
@endsection

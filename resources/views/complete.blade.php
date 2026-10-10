@extends('ext-userregister::layout')
@section('title', $verified ? 'Finish your account' : 'Create your account')
@section('body')
    @if ($verified)
        <p class="ur-lead">Email verified: <span class="ur-email">{{ $email }}</span><br>Choose your account details to get started.</p>
    @else
        <p class="ur-lead">Enter your details to create an account.</p>
    @endif
    <form method="post" action="{{ $verified ? '/extensions/userregister/complete' : '/extensions/userregister/register' }}">
        @csrf
        @unless ($verified)
            <div class="ur-field">
                <label for="ur-email">Email address</label>
                <input id="ur-email" name="email" type="email" autocomplete="email" maxlength="191" required value="{{ old('email') }}">
            </div>
        @endunless
        <div class="ur-field">
            <label for="ur-first">First name</label>
            <input id="ur-first" name="name_first" autocomplete="given-name" maxlength="191" required value="{{ old('name_first') }}">
        </div>
        <div class="ur-field">
            <label for="ur-last">Last name</label>
            <input id="ur-last" name="name_last" autocomplete="family-name" maxlength="191" required value="{{ old('name_last') }}">
        </div>
        <div class="ur-field">
            <label for="ur-username">Username</label>
            <input id="ur-username" name="username" autocomplete="username" maxlength="191" required aria-describedby="ur-username-help" value="{{ old('username') }}">
            <p class="ur-note" id="ur-username-help">Use at least 3 letters or numbers. Dashes, underscores, and periods are allowed between them.</p>
        </div>
        <div class="ur-field">
            <label for="ur-password">Password</label>
            <input id="ur-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="1024" required aria-describedby="ur-password-help">
            <p class="ur-note" id="ur-password-help">Use at least 8 characters.</p>
        </div>
        <div class="ur-field">
            <label for="ur-confirm">Confirm password</label>
            <input id="ur-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="1024" required>
        </div>
        @if ($policies !== [])
            <div class="ur-policies">
                <label><input type="checkbox" name="accept_policies" value="1" required>
                    <span>I have read and agree to the @foreach ($policies as $label => $url)@if (!$loop->first) and @endif<a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>@endforeach.</span>
                </label>
            </div>
        @endif
        <button class="ur-button" type="submit">Create account</button>
    </form>
@endsection

@extends('ext-userregister::layout')
@section('title', 'Create your account')
@section('body')
    <p class="ur-lead">Enter your email to receive a verification link and get started.</p>
    <form method="post" action="/extensions/userregister/request">
        @csrf
        <div class="ur-field">
            <label for="ur-email">Email address</label>
            <input id="ur-email" name="email" type="email" autocomplete="email" maxlength="191" required value="{{ old('email') }}">
        </div>
        <button class="ur-button" type="submit">Send verification email</button>
    </form>
@endsection

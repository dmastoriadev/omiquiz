@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Choose a new password</h1>
    <p class="mt-2 text-sm leading-6 text-slate-500">Use at least 12 characters for your new password.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="oq-label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" maxlength="255" required class="oq-input">
        </div>
        <div>
            <label for="password" class="oq-label">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required autofocus class="oq-input">
        </div>
        <div>
            <label for="password_confirmation" class="oq-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required class="oq-input">
        </div>
        <button type="submit" class="oq-button-primary w-full">Save new password</button>
    </form>
@endsection

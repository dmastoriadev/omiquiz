@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Reset your password</h1>
    <p class="mt-2 text-sm leading-6 text-slate-500">Enter your account email to request a password reset link.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
        @csrf
        <div>
            <label for="email" class="oq-label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus class="oq-input">
        </div>
        <button type="submit" class="oq-button-primary w-full">Send reset link</button>
    </form>
    <a href="{{ route('login') }}" class="mt-6 block text-center text-sm font-medium text-slate-600 hover:text-indigo-600">Back to sign in</a>
@endsection

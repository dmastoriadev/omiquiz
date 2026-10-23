@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
    <p class="mt-2 text-sm leading-6 text-slate-500">Sign in to your administrator account.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
        @csrf
        <div>
            <label for="email" class="oq-label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus class="oq-input">
        </div>
        <div>
            <label for="password" class="oq-label">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="oq-input">
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <label for="remember" class="inline-flex items-center gap-2 text-slate-600">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-slate-300 accent-indigo-600">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-indigo-600 hover:text-indigo-700 hover:underline">Forgot password?</a>
        </div>
        <button type="submit" class="oq-button-primary w-full">Sign in</button>
    </form>
@endsection

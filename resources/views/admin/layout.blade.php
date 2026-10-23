<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Overview') · omiquiz</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded-lg focus:bg-white focus:p-4">Skip to content</a>
    <aside class="border-b border-slate-200 bg-white lg:fixed lg:inset-y-0 lg:left-0 lg:flex lg:w-60 lg:flex-col lg:border-b-0 lg:border-r">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-6 py-6" aria-label="omiquiz overview">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-xl font-semibold text-white" aria-hidden="true">o</span>
            <span class="text-2xl font-semibold tracking-tight">omiquiz</span>
        </a>
        <nav aria-label="Administration" class="flex flex-wrap gap-2 px-4 pb-4 lg:flex-col">
            <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif class="rounded-xl px-4 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Overview</a>
            <a href="{{ route('admin.games.index') }}" @if(request()->routeIs('admin.games.*')) aria-current="page" @endif class="rounded-xl px-4 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.games.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Games</a>
            <a href="{{ route('admin.sessions.index') }}" @if(request()->routeIs('admin.sessions.*')) aria-current="page" @endif class="rounded-xl px-4 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.sessions.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Sessions &amp; results</a>
            <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif class="rounded-xl px-4 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.users.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Administrators</a>
        </nav>
        <div class="hidden border-t border-slate-100 px-6 py-5 text-xs leading-5 text-slate-500 lg:mt-auto lg:block">Quiz administration</div>
    </aside>

    <div class="lg:pl-60">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4 sm:px-8">
            <p class="text-sm font-medium text-slate-500">Administration</p>
            <div class="flex items-center gap-4">
                <span class="max-w-40 truncate text-sm text-slate-600 sm:max-w-xs">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Sign out</button>
                </form>
            </div>
        </header>
        <main id="main-content" class="mx-auto max-w-7xl px-5 py-7 sm:px-8 sm:py-9">
            @include('partials.feedback')
            @yield('content')
        </main>
    </div>
</body>
</html>

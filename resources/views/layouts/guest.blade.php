<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Sign in') · omiquiz</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
        <div class="w-full max-w-md">
            <div class="mb-9 flex items-center justify-center gap-3" aria-label="omiquiz">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-xl font-semibold text-white" aria-hidden="true">o</span>
                <span class="text-2xl font-semibold tracking-tight">omiquiz</span>
            </div>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @include('partials.feedback')
                @yield('content')
            </section>
            <p class="mt-6 text-center text-xs text-slate-500">omiquiz administration</p>
        </div>
    </main>
</body>
</html>

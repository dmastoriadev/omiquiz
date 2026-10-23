<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Play') · omiquiz</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="oq-public-shell min-h-screen">
    <main class="oq-public-main mx-auto @yield('page-width', 'max-w-4xl') px-4 pb-8 sm:px-6">@include('partials.feedback')@yield('content')</main>
    <noscript><p class="mx-auto max-w-xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Please enable JavaScript to play or watch live quiz progress.</p></noscript>
</body>
</html>

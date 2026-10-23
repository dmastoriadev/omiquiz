@extends('layouts.guest')

@section('title', 'Page not found')

@section('content')
    <p class="text-sm font-semibold text-indigo-600">404</p>
    <h1 class="mt-3 text-2xl font-semibold tracking-tight">Page not found</h1>
    <p class="mt-3 text-sm leading-6 text-slate-500">The page you requested could not be found.</p>
    <a href="{{ route('home') }}" class="oq-button-primary mt-7">Return to omiquiz</a>
@endsection

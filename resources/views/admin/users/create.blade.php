@extends('admin.layout')

@section('title', 'Add administrator')

@section('content')
    <div class="mb-7">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-slate-500 hover:text-indigo-600">Back to administrators</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">Add administrator</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Give someone access to manage omiquiz.</p>
    </div>
    @include('admin.users._form', ['action' => route('admin.users.store'), 'method' => 'POST'])
@endsection

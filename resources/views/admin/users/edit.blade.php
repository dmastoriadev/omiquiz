@extends('admin.layout')

@section('title', 'Edit administrator')

@section('content')
    <div class="mb-7">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-slate-500 hover:text-indigo-600">Back to administrators</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">Edit administrator</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Update {{ $user->name }}’s account details.</p>
    </div>
    @include('admin.users._form', ['action' => route('admin.users.update', $user), 'method' => 'PUT'])
@endsection

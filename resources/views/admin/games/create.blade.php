@extends('admin.layout')
@section('title', 'Create game')
@section('content')
    <div class="mb-7"><a href="{{ route('admin.games.index') }}" class="text-sm font-medium text-indigo-600">← Games</a><h1 class="mt-4 text-3xl font-semibold tracking-tight">Create a game</h1><p class="mt-2 text-sm text-slate-500">Choose a category and its rules. Upload questions or drawing words on the next screen.</p></div>
    @include('admin.games._form', ['action' => route('admin.games.store'), 'method' => 'POST'])
@endsection

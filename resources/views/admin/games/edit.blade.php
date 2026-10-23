@extends('admin.layout')
@section('title', 'Edit game')
@section('content')
    <div class="mb-7"><a href="{{ route('admin.games.show', $game) }}" class="text-sm font-medium text-indigo-600">← Back to game</a><h1 class="mt-4 text-3xl font-semibold tracking-tight">Edit game</h1></div>
    @include('admin.games._form', ['action' => route('admin.games.update', $game), 'method' => 'PUT'])
@endsection

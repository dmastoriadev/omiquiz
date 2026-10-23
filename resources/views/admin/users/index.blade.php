@extends('admin.layout')

@section('title', 'Administrators')

@section('content')
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Administrators</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Manage access to your omiquiz administration.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="oq-button-primary">Add administrator</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-5 flex max-w-lg flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <label for="search" class="oq-label">Search administrators</label>
            <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name or email address" class="oq-input">
        </div>
        <button type="submit" class="oq-button-secondary">Search</button>
        @if ($search !== '')
            <a href="{{ route('admin.users.index') }}" class="py-2.5 text-sm font-medium text-slate-500 hover:text-indigo-600">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-4 font-medium sm:px-6">Administrator</th>
                        <th scope="col" class="px-5 py-4 font-medium sm:px-6">Email</th>
                        <th scope="col" class="px-5 py-4 text-right font-medium sm:px-6">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-5 py-4 sm:px-6">
                                <span class="font-medium">{{ $user->name }}</span>
                                @if ($user->is(auth()->user()))
                                    <span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">You</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500 sm:px-6">{{ $user->email }}</td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-indigo-600 hover:underline">Edit<span class="sr-only"> {{ $user->name }}</span></a>
                                    <form method="POST" action="{{ route('admin.users.send-reset-password', $user) }}">
                                        @csrf
                                        <button type="submit" class="whitespace-nowrap font-medium text-slate-600 hover:underline">Send reset link<span class="sr-only"> to {{ $user->name }}</span></button>
                                    </form>
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this administrator account? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-rose-600 hover:underline">Delete<span class="sr-only"> {{ $user->name }}</span></button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-slate-500">No administrators match your search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">{{ $users->links() }}</div>
        @endif
    </div>
@endsection

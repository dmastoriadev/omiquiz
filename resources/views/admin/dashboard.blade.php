@extends('admin.layout')

@section('title', 'Overview')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4"><div>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Overview</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Your games, questions, and quiz activity at a glance.</p></div><div class="flex gap-3"><a href="{{ route('admin.sessions.index') }}" class="oq-button-secondary">View results</a><a href="{{ route('admin.games.create') }}" class="oq-button-primary">Create game</a></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['games' => 'Games', 'questions' => 'Questions', 'sessions' => 'Game sessions', 'completed_sessions' => 'Completed sessions'] as $key => $label)
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-medium text-slate-500">{{ $label }}</h2>
                <p class="mt-3 text-3xl font-semibold tracking-tight">{{ number_format($stats[$key]) }}</p>
            </section>
        @endforeach
    </div>

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="recent-sessions-title">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <h2 id="recent-sessions-title" class="text-base font-semibold">Recent sessions</h2>
            <p class="mt-1 text-sm text-slate-500">The latest activity across your quiz games.</p>
        </div>
        @if ($recentSessions->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="font-medium text-slate-700">No sessions yet</p>
                <p class="mt-2 text-sm text-slate-500">Your latest quiz sessions will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Game</th>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Status</th>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Created (UTC)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentSessions as $session)
                            <tr>
                                <td class="px-5 py-4 font-medium sm:px-6"><a href="{{ route('admin.sessions.show', $session) }}" class="hover:text-indigo-600">{{ $session->game_title }}</a></td>
                                <td class="px-5 py-4 sm:px-6">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($session->status)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-500 sm:px-6">{{ $session->created_at?->utc()->format('M j, Y · H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

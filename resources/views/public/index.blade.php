@extends('play.layout')
@section('title', 'Live game arena')
@section('page-width', 'max-w-6xl')
@section('content')
    <section class="relative overflow-hidden rounded-3xl bg-slate-900 p-7 text-white sm:p-12">
        <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full border border-indigo-400/20" aria-hidden="true"></div><div class="pointer-events-none absolute -bottom-36 right-12 h-80 w-80 rounded-full border border-indigo-400/20" aria-hidden="true"></div>
        <div class="relative max-w-2xl"><p class="text-xs font-semibold uppercase tracking-widest text-indigo-300">The omiquiz arena</p><h1 class="mt-4 text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">Your next quest<br>starts around the campfire.</h1><p class="mt-5 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">Test your knowledge in a quiz or draw your way to victory in Sketch & Guess. Or watch the action and cheer on the players.</p><a href="#open-games" class="oq-button-primary mt-7">Find a game ↓</a></div>
    </section>

    <section id="open-games" class="mt-10 scroll-mt-6">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-2xl font-semibold tracking-tight">Open arena</h2><p class="mt-2 text-sm text-slate-500">Waiting lobbies and games in progress. Spectating does not take a player spot.</p></div><a href="{{ url()->current() }}" class="oq-button-secondary">Refresh list</a></div>
        @if($sessions->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><p class="text-lg font-semibold">The arena is quiet</p><p class="mt-2 text-sm leading-6 text-slate-500">A host needs to create a session before players can join. Check back soon.</p><a href="{{ route('login') }}" class="mt-5 inline-block text-sm font-semibold text-indigo-600">Host a game →</a></div>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach($sessions as $session)
                    @php($live = $session->status === 'in_progress')
                    <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6">
                        <div class="flex items-center justify-between gap-3"><span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $live ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700' }}"><span class="h-1.5 w-1.5 rounded-full {{ $live ? 'bg-emerald-500' : 'bg-indigo-500' }}" aria-hidden="true"></span>{{ $live ? 'Live now' : 'Players gathering' }}</span><span class="text-xs tabular-nums text-slate-400">{{ $session->players_count }}/{{ $session->max_players }} players</span></div>
                        <p class="mt-4 text-[10px] font-semibold uppercase tracking-widest text-indigo-600">{{ $session->game_type === 'sketch' ? 'Sketch & Guess' : 'Quiz' }}</p><h3 class="mt-2 break-words text-xl font-semibold leading-7"><a href="{{ route('public.sessions.show', $session) }}" class="hover:text-indigo-600">{{ $session->game_title }}</a></h3>
                        <p class="mt-2 text-sm text-slate-400">{{ $session->game_type === 'sketch' ? ($session->total_questions > 0 ? $session->total_questions.' drawing turns' : 'All uploaded words') : $session->total_questions.' questions' }} · {{ $session->time_limit_per_question }} seconds each</p>
                        <p class="mb-6 mt-6 text-xs font-medium text-slate-500">{{ $live ? ($session->game_type === 'sketch' ? 'Turn ' : 'Question ').$session->current_question_position.' of '.$session->total_questions : 'Waiting for the host' }}</p>
                        <div class="mt-auto flex flex-wrap gap-2"><a href="{{ route('public.sessions.show', $session) }}" class="oq-button-secondary flex-1">{{ $live ? 'Watch live' : 'View player queue' }}</a>@if(!$live && $session->players_count < $session->max_players)<a href="{{ route('play.show', $session) }}" class="oq-button-primary flex-1">Join game</a>@elseif(!$live)<span class="inline-flex items-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-400">Lobby full</span>@endif</div>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $sessions->links() }}</div>
        @endif
    </section>

    @if(($recentCompleted ?? collect())->isNotEmpty())
        <section class="mt-10"><div class="mb-5"><h2 class="text-xl font-semibold">Recently completed</h2><p class="mt-2 text-sm text-slate-500">See who claimed the top spot.</p></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($recentCompleted as $completed)<a href="{{ route('public.sessions.show', $completed) }}" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300"><p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Final results</p><h3 class="mt-3 break-words font-semibold">{{ $completed->game_title }}</h3><p class="mt-2 text-xs text-slate-400">{{ $completed->completed_at?->utc()->format('M j · H:i') }} UTC</p><span class="mt-4 inline-block text-sm font-semibold text-indigo-600">View standings →</span></a>@endforeach</div></section>
    @endif
@endsection

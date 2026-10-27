@extends('play.layout')
@section('title', 'Watch '.$session->game_title)
@section('page-width', 'max-w-none')
@section('content')
    <div class="spectator-page" x-data="quizBoard(@js($state), @js(['state' => route('public.sessions.state', $session), 'drawingRead' => route('public.sessions.drawing', $session)]))" @sketch-state="applyState($event.detail)" @slots-state="applyState($event.detail)" :data-motion-paused="motionPaused ? 'true' : 'false'">
        <div class="spectator-heading">
            <div class="min-w-0"><p class="spectator-eyebrow" x-text="state.status === 'waiting' ? 'Spectator view / Waiting lobby' : 'Spectator view / ' + statusLabel"></p><h1 class="mt-1 break-words text-3xl font-bold tracking-tight sm:text-4xl">{{ $session->game_title }}</h1></div>
            <span class="spectator-status"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span><span x-text="state.status === 'waiting' ? 'Waiting for host' : statusLabel"></span></span>
        </div>
        <p x-show="connectionError" x-cloak role="status" class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800" x-text="connectionError"></p>

        @include('play._camp', ['playerView' => false])
        @include('play._answer-reveal')
        @include('play._round-leaderboard')
        @if ($session->game_type === 'sketch')@include('play._sketch')@endif
        @if ($session->game_type === 'slots')@include('play._slots')@endif
        @if ($session->game_type === 'bingo')@include('play._bingo', ['hostView' => false])@endif

        <div x-show="isQuestionPhase" x-cloak class="space-y-6">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="p-6 sm:p-8"><div class="flex items-center justify-between gap-4"><p class="text-xs font-semibold uppercase tracking-widest text-indigo-600" x-text="'Question ' + state.current_position + ' of ' + state.total_questions"></p><p class="rounded-full px-4 py-2 text-2xl font-semibold tabular-nums" :class="remainingSeconds <= 5 ? 'bg-rose-50 text-rose-600' : 'bg-indigo-50 text-indigo-700'"><span x-text="remainingSeconds"></span><span class="ml-1 text-xs font-normal">sec</span></p></div><h2 class="mt-6 break-words whitespace-pre-line text-2xl font-semibold leading-9 sm:text-3xl sm:leading-10" x-text="state.question?.text || 'Loading the next question…'"></h2><div class="mt-6 grid gap-3 sm:grid-cols-2"><template x-for="[key, value] in Object.entries(state.question?.options || {})" :key="key"><div class="flex items-start gap-3 rounded-xl border border-slate-200 p-4"><span class="font-semibold text-indigo-600" x-text="key"></span><span class="min-w-0 break-words text-sm leading-6" x-text="value"></span></div></template></div></div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4"><p class="text-xs font-medium text-slate-500">A check means this question has been answered.</p><span class="text-xs text-slate-400">Spectator mode</span></div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold">The party is thinking…</h2><p class="mt-1 text-xs text-slate-400">Answers are locked in until the next question.</p></div><button type="button" class="text-xs font-semibold text-slate-500 hover:text-indigo-600" @click="toggleMotion()" :aria-pressed="motionPaused" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button></div><div class="answer-party-grid"><template x-for="person in state.players" :key="person.public_id"><article class="answer-party-card" :class="answeredCurrent(person) ? 'answer-party-card--answered' : ''"><div class="answer-party-avatar"><img :src="person.profile?.sprite_url || person.profile?.avatar_url" alt="" width="100" height="100" class="party-float" :style="campStyle(person.public_id)"><template x-for="token in (answeredCurrent(person) ? [checkKey(person)] : [])" :key="token"><span class="answer-check answer-check--pulse" role="img" :aria-label="person.name + ' answered this question'"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4 10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span></template></div><h3 class="answer-party-name" :title="person.name" x-text="person.name"></h3><p class="mt-1 text-[10px] font-medium" :class="answeredCurrent(person) ? 'text-emerald-600' : 'text-slate-400'" x-text="answeredCurrent(person) ? 'Answered' : 'Thinking…'"></p></article></template></div></section>
        </div>

        @if ($session->game_type === 'bingo')
            @include('play._bingo-results')
        @elseif ($session->game_type !== 'slots')
            @include('play._results')
        @endif
        <section x-show="state.status === 'cancelled'" x-cloak class="rounded-2xl border border-slate-200 bg-white p-8"><h2 class="text-2xl font-semibold" x-text="state.game_type === 'slots' && state.slots_restart_required === true ? 'A new Slots session is needed' : 'This quest has ended early'">This quest has ended early</h2><p class="mt-3 text-sm leading-6 text-slate-500" role="status" x-text="state.game_type === 'slots' && state.slots_restart_required === true ? (state.slots_restart_message || 'This session used the previous small-player rules and cannot continue. The host needs to create a new Slots session with at least 6 players.') : 'The host cancelled this session. Visit the arena to find another game.'">The host cancelled this session. Visit the arena to find another game.</p><a href="{{ route('home') }}" class="oq-button-secondary mt-5">Browse games</a></section>
    </div>
@endsection

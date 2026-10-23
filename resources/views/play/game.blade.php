@extends('play.layout')
@section('title', $session->game_title)
@section('page-width', 'max-w-none')
@section('content')
    <div class="spectator-page" x-data="quizBoard(@js($state), @js(['state' => route('play.state', $session), 'answer' => route('play.answer', $session), 'profile' => route('play.profile', $session), 'ready' => route('play.ready', $session), 'guess' => route('play.sketch.guess', $session), 'hint' => route('play.sketch.hint', $session), 'drawingWrite' => route('play.sketch.draw', $session), 'drawingRead' => route('public.sessions.drawing', $session)]))" @sketch-state="applyState($event.detail)" :data-motion-paused="motionPaused ? 'true' : 'false'">
        <div class="spectator-heading">
            <div class="min-w-0"><p class="spectator-eyebrow" x-text="state.status === 'waiting' ? 'Player view / Waiting lobby' : 'Player view / ' + statusLabel"></p><h1 class="mt-1 break-words text-3xl font-bold tracking-tight sm:text-4xl">{{ $session->game_title }}</h1></div>
            <span class="spectator-status"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span><span x-text="state.status === 'waiting' ? 'Waiting for host' : statusLabel"></span></span>
        </div>
        <p x-show="connectionError" x-cloak role="status" class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800" x-text="connectionError"></p>
        <p x-show="error" x-cloak role="alert" class="mb-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800" x-text="error"></p>
        @include('play._camp', ['playerView' => true])
        <div class="player-game-content" x-show="state.status !== 'waiting'" x-cloak>
            @include('play._round-leaderboard')
            @if ($session->game_type === 'sketch')@include('play._sketch')@endif
            <section x-show="isQuestionPhase" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-indigo-100 bg-white px-5 py-3" aria-label="Your player profile"><img :src="state.player?.profile?.avatar_url" alt="Your assigned avatar" width="44" height="44" class="h-11 w-11 shrink-0 rounded-xl bg-indigo-50"><div class="min-w-0 flex-1"><p class="text-xs text-slate-400">Playing as</p><h2 class="break-words text-sm font-semibold" x-text="state.player?.name"></h2></div><p class="text-xs text-slate-400" x-text="state.player?.gender || 'Not specified'"></p><a href="{{ route('public.sessions.show', $session) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-indigo-600">Spectator view ↗</a></section>
        <section x-show="isQuestionPhase" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="p-6 sm:p-8"><div class="flex items-center justify-between gap-4"><p class="text-xs font-semibold uppercase tracking-widest text-indigo-600" x-text="'Question ' + state.current_position + ' of ' + state.total_questions"></p><p class="rounded-full px-4 py-2 text-2xl font-semibold tabular-nums" :class="remainingSeconds <= 5 ? 'bg-rose-50 text-rose-600' : 'bg-indigo-50 text-indigo-700'"><span x-text="remainingSeconds"></span><span class="ml-1 text-xs font-normal">sec</span></p></div>
                <h1 class="mt-7 break-words whitespace-pre-line text-2xl font-semibold leading-9 sm:text-3xl sm:leading-10" x-text="state.question?.text || 'Loading the next question…'"></h1>
                <div class="mt-7 grid gap-3 sm:grid-cols-2"><template x-for="[key, value] in Object.entries(state.question?.options || {})" :key="state.question.id + '-' + key"><button type="button" @click="selectOption(key)" :disabled="answerLocked || submitting || remainingSeconds === 0 || !state.question" :aria-pressed="selectedOption === key" :class="selectedOption === key ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-slate-200 bg-white enabled:hover:border-indigo-400 enabled:hover:bg-indigo-50/50'" class="flex min-h-20 items-center gap-4 rounded-xl border p-4 text-left text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-default"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-semibold text-slate-600" x-text="key"></span><span class="min-w-0 break-words text-base leading-6" x-text="value"></span></button></template></div>
            </div>
            <div class="answer-submit-panel">
                <div><p class="text-sm font-semibold text-slate-700" role="status" x-text="submitting ? 'Submitting your answer…' : state.answered ? 'Submitted — answer locked.' : answerPending ? 'Submission not yet confirmed. Your chosen answer is locked; retry to confirm.' : remainingSeconds === 0 ? 'Time is up. Preparing the leaderboard…' : 'Tap an option, then Submit answer. You can change your choice before submitting.'"></p><p x-show="!state.answered && !answerPending" class="mt-1 text-xs leading-5 text-slate-500">An unsubmitted selection counts as unanswered when time runs out.</p></div>
                <button x-show="!answerPending" type="button" @click="submitAnswer()" :disabled="!canSubmit" class="oq-button-primary answer-submit-button" x-text="submitting ? 'Submitting…' : state.answered ? 'Submitted ✓' : 'Submit answer'"></button><button x-show="answerPending && isQuestionPhase && remainingSeconds > 0" type="button" @click="retryAnswer()" :disabled="!canRetryAnswer" class="oq-button-primary answer-submit-button" x-text="submitting ? 'Submitting…' : 'Retry submission'"></button>
            </div>
        </section>
        @include('play._results')
        <section x-show="state.status === 'cancelled'" x-cloak class="rounded-2xl border border-slate-200 bg-white p-8 text-center"><h1 class="text-2xl font-semibold">The host ended this session</h1><p class="mt-3 text-sm text-slate-500">Ask your host for a new link to play again.</p></section>
        </div>
    </div>
@endsection
